#!/usr/bin/env python3

import re
import telebot
import datetime
import paramiko
import sqlite3
import socket

from nemuno_config import token, admin_chatid, dbfile, servers
from nemuno_l10n import _, message_start, message_help, message_adminhelp, langs

db = sqlite3.connect(dbfile, check_same_thread = False)
db.execute('CREATE TABLE IF NOT EXISTS timeout  (id varchar(255), data varchar(255));')
db.execute('CREATE TABLE IF NOT EXISTS declined (id varchar(255), data varchar(255));')
db.execute('CREATE TABLE IF NOT EXISTS langs    (id varchar(255), data varchar(255));')

bot = telebot.TeleBot(token);

owner = '@' + bot.get_chat(admin_chatid).username

serv_kbd = telebot.types.ReplyKeyboardMarkup()
for server in servers.keys():
    serv_kbd.add(telebot.types.KeyboardButton(server.title()))

lang_kbd = telebot.types.ReplyKeyboardMarkup()
for lang in langs:
    lang_kbd.add(telebot.types.KeyboardButton(lang.upper()))

def send(to, msg, reply_markup = telebot.types.ReplyKeyboardRemove()):
    print('Message for ' + str(to) + ': ' + msg, flush = True)
    bot.send_message(to, msg, parse_mode = 'HTML', reply_markup = reply_markup)

def sane(user):
    return re.fullmatch(r'[A-Za-z][A-Za-z0-9_]{2,31}', user)

def read_db(table):
    try:
        bundle = db.execute('SELECT * FROM ' + table).fetchall()
    except DatabaseError:
        return False
    return dict(bundle)

def get_db(id, table):
    try:
        bundle = db.execute('SELECT data FROM ' + table + ' WHERE id = \'' + id + '\'').fetchall()
    except sqlite3.DatabaseError:
        return False
    if len(bundle) == 0:
        return ''
    else:
        return bundle[0][0]

def put_db(id, data, table):
    try:
        bundle = db.execute('SELECT data FROM ' + table + ' WHERE id = \'' + id + '\'').fetchall()
        if len(bundle) == 0:
            db.execute('INSERT INTO ' + table + ' VALUES (?, ?)', (id, data))
        else:
            db.execute('UPDATE ' + table + ' SET data = \'' + data + '\' WHERE id = \'' + id + '\'')
        db.commit()
        return True
    except sqlite3.DatabaseError:
        return False

def remove_db(id, table):
    try:
        db.execute('DELETE FROM ' + table + ' WHERE id = \'' + id + '\'')
        db.commit()
        return True
    except sqlite3.DatabaseError:
        return False

def ssh_run(server, cmds):
    client = paramiko.SSHClient()
    client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        client.connect(hostname = servers[server]['host'], username = servers[server]['user'], port = servers[server]['port'], key_filename = servers[server]['key'], disabled_algorithms = servers[server]['disabled_algorithms'] if('disabled_algorithms' in servers[server].keys()) else dict(), timeout = 5.0, banner_timeout = 5.0, auth_timeout = 5.0)
    except (paramiko.ssh_exception.SSHException, socket.timeout):
        client.close()
        return False
    outputs = []
    for command in cmds:
        try:
            stdin, stdout, stderr = client.exec_command(command['cmd'], timeout = 5.0)
            output = stdout.read().decode('utf-8')
        except (paramiko.ssh_exception.SSHException, socket.timeout):
            output = command['errmsg']
        outputs.append(output)
    client.close()
    return outputs

def get_user(user, server):
    results = ssh_run(server, [ {'cmd': 'id -u ' + user + ' || echo -1', 'errmsg': '-1'} ])
    if results == False:
        return False
    try:
        uid = int(results[0])
    except ValueError:
        return 'no'
    return 'no' if uid < 0 else 'yes'

def state(l, user):
    user = user.lower()
    reason = get_db(user, 'declined')
    if reason:
        return _(l, 'User ') + user + _(l, ' is not added because of: ') + reason + '.'
    active = []
    missing = ''
    for server in servers.keys():
        st = get_user(user, server)
        if st == False:
            missing += _(l, 'Note: Server ') + server + _(l, ' is offline, can\'t check it.') + '\n'
        elif st == 'yes':
            active.append(server)
    if not active:
        return missing + _(l, 'User ') + user + _(l, ' is not added on any online servers yet.')
    if len(active) > 1:
        return missing + _(l, 'User ') + user + _(l, ' is active on following online servers: ') + ', '.join(active)
    else:
        return missing + _(l, 'User ') + user + _(l, ' is active on the online server ') + active[0]

def info(l, server):
    results = ssh_run(server, [ {'cmd': 'uptime || echo ""', 'errmsg': _(l, 'Server ') + server + _(l, ' can\'t run uptime')}, {'cmd': 'mpstat -u 1 1', 'errmsg': _(l, 'Server ') + server + _(l, ' can\'t run mpstat')},  ])
    if results == False:
        return _(l, 'Server ') + server + _(l, ' is offline.')
    return _(l, 'Server ') + server + _(l, ' statistics:') + '\n\n' + _(l, 'Uptime:') + ' <code>' + results[0] + '</code>\n' + _(l, 'Load:') + '\n<pre>' + results[1] + '</pre>'

def decline(l, args):
    try:
        user, reason = args.split(maxsplit=1)
    except ValueError:
        return _(l, 'You must specify a reason.')
    user = user.lower()
    if not sane(user):
        return user + _(l, ' is not an allowed username.')
    if put_db(user, reason, 'declined'):
        return _(l, 'User ') + user + _(l, ' is now declined due to: ') + reason
    else:
        return _(l, 'Database error. Tell ') + owner + _(l, ' about it, please.')

def undecline(l, user):
    user = user.lower()
    if not sane(user):
        return user + _(l, ' is not an allowed username.')
    if remove_db(user, 'declined'):
        return _(l, 'User ') + user + _(l, ' is now undeclined.')
    else:
        return _(l, 'Database error. Tell ') + owner + _(l, ' about it, please.')

def set_lang(to, u, l, args):
    args = args.lower()
    if args not in langs:
        send(to, _(l, 'Wrong language: ') + args)
        return l
    else:
        put_db(u, args, 'langs')
        send(to, _(args, 'Language set: ') + args)
        return args

def ready(to, l, u):
    now = datetime.datetime.now()
    timeout = get_db(u, 'timeout')
    if timeout == False:
        send(to, _(l, 'Database error. Tell ') + owner + _(l, ' about it, please.'))
        return False
    if timeout != '':
        timeout = datetime.datetime.fromtimestamp(float(timeout))
        if (now - timeout).total_seconds() < 10:
            send(to, _(l, 'Please wait a bit more.'))
            return False
    if not put_db(u, str(now.timestamp()), 'timeout'):
        send(to, _(l, 'Database error. Tell ') + owner + _(l, ' about it, please.'))
        return False
    send(to, _(l, 'Checking...'))
    return True

def run_state(to, l, u, args):
    if not sane(args):
        send(to, args + _(l, ' is not an allowed username.'))
    elif ready(to, l, u):
        send(to, state(l, args))

def run_info(to, l, u, args):
    args = args.lower()
    if args not in servers.keys():
        send(to, _(l, 'This server does not exist. You may try ') + ', '.join(servers.keys()) + '.')
    elif ready(to, l, u):
        send(to, info(l, args))

user_langs = read_db('langs');

statemachine = {}

@bot.message_handler(content_types=['text'])
def get_text_messages(message):
    to = message.chat.id
    u = str(to)
    l = user_langs[u] if u in user_langs.keys() else langs[0]
    print('Message from ' + u + ' (' + str(message.chat.username) + '), user ' + str(message.from_user.id) + ' (' + str(message.from_user.username) + '): ' + message.text, flush = True)
    if not to in statemachine.keys():
        statemachine[to] = 'cmd'

    if statemachine[to] == 'cmd':
        try:
            cmd, args = message.text.split(maxsplit=1)
        except ValueError:
            cmd = message.text
            args = ''
        cmd = re.match(r'^[^@]*', cmd)[0]

        if cmd == '/start':
            send(to, _(l, message_start) + '\n' + _(l, 'Contact ') + owner + _(l, ' for further info.'))

        elif cmd == '/help':
            if u == admin_chatid:
                send(to, _(l, message_adminhelp));
            else:
                send(to, _(l, message_help))

        elif cmd == '/lang':
            if args == "":
                send(to, _(l, 'Now specify your language (' + '/'.join(langs) + '):'), reply_markup = lang_kbd)
                statemachine[to] = 'lang'
            else:
                l = user_langs[u] = set_lang(to, u, l, args)

        elif cmd == '/state':
            if args == "":
                send(to, _(l, 'Now specify username to query:'))
                statemachine[to] = 'state'
            else:
                run_state(to, l, u, args)

        elif cmd == '/info':
            if args == "":
                send(to, _(l, 'Now specify server name to query:'), reply_markup = serv_kbd)
                statemachine[to] = 'info'
            else:
                run_info(to, l, u, args)

        elif cmd == '/decline' and u == admin_chatid:
            send(to, decline(l, args))

        elif cmd == '/undecline' and u == admin_chatid:
            send(to, undecline(l, args))

        else:
            send(to, _(l, 'Wrong command, type /help to get help on commands.'))

    elif statemachine[to] == 'info':
        run_info(to, l, u, message.text)
        statemachine[to] = 'cmd'

    elif statemachine[to] == 'state':
        run_state(to, l, u, message.text)
        statemachine[to] = 'cmd'

    elif statemachine[to] == 'lang':
        l = user_langs[u] = set_lang(to, u, l, message.text)
        statemachine[to] = 'cmd'

bot.polling(none_stop=True, interval=1)