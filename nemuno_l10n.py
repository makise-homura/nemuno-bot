#!/usr/bin/env python3

langs = [ 'en', 'ru' ] # First one is default

message_help = '''Supported commands:
/start: Allow me to send you messages. Unless you say this, I can't communicate with you.
/lang ''' + '|'.join(langs) + ''': Set preferred language.
/state &lt;user&gt;: Check if user is created on the servers (no more than 1 request in 10 seconds allowed).
/info &lt;server&gt;: Check uptime and current load of server (no more than 1 request in 10 seconds allowed).
/help: Get this help.'''

message_adminhelp = '''Supported commands:
/start: Allow me to send you messages. Unless you say this, I can't communicate with you.
/lang ''' + '|'.join(langs) + ''': Set preferred language.
/state &lt;user&gt;: Check if user is created on the servers.
/decline &lt;user&gt; &lt;reason&gt;: Decline user with some reason.
/undecline &lt;user&gt;: Remove decline reason for user.
/info &lt;server&gt;: Check uptime and current load of server.
/help: Get this help.'''

message_start = '''Welcome!
I'm Nemuno from Mount Elbrus (either 8C or 8CV peak), and I will help you perform some actions on them.
Say /help to check what's available for you.
Say /lang to select the language (currently supported: ''' + ', '.join(langs) + ''').'''

message_help_ru = '''Поддерживаемые команды:
/start: Разрешить мне посылать сообщения. Без этого я не смогу ничего отправить.
/lang ''' + '|'.join(langs) + ''': Установить язык.
/state &lt;user&gt;: Проверить, создан ли пользователь на серверах (не чаще 1 запроса в 10 секунд).
/info &lt;server&gt;: Проверить аптайм и загруженность сервера (не чаще 1 запроса в 10 секунд).
/help: Эта справка.'''

message_adminhelp_ru = '''Поддерживаемые команды:
/start: Разрешить мне посылать сообщения. Без этого я не смогу ничего отправить.
/lang ''' + '|'.join(langs) + ''': Установить язык.
/state &lt;user&gt;: Проверить, создан ли пользователь на серверах (не чаще 1 запроса в 10 секунд).
/decline &lt;user&gt; &lt;reason&gt;: Отклонить создание пользователя по какой-либо причине.
/undecline &lt;user&gt;: Убрать причину отклонения пользователя.
/info &lt;server&gt;: Проверить аптайм и загруженность сервера (не чаще 1 запроса в 10 секунд).
/help: Эта справка.'''

message_start_ru = '''Привет!
Я Немуно с Эльбруса (какая-то из вершин 8С или 8СВ), и я смогу помочь сделать кое-что с ними.
Мне можно сказать /help, чтобы увидеть доступные команды.
А также можно сказать /lang, чтобы выбрать язык (имеются: ''' + ', '.join(langs) + ''').'''

l10n = {
    'ru': {
        message_help: message_help_ru,
        message_adminhelp: message_adminhelp_ru,
        message_start: message_start_ru,
        ' is not an allowed username.':                         ' не является годным именем пользователя.',
        'Database error. Tell ':                                'Поломалась база данных. Сообщи ',
        ' about it, please.':                                   ' про это, пожалуйста.',
        'Please wait a bit more.':                              'Не так быстро, подожди ещё чуть-чуть.',
        'User ':                                                'Пользователь ',
        ' is not added because of: ':                           ' не будет зарегистрирован по причине: ',
        ' is not added on any online servers yet.':             ' пока не добавлен ни на один из серверов.',
        ' is active on following online servers: ':             ' добавлен на следующие сервера: ',
        ' is active on the online server ':                     ' добавлен на сервер ',
        ' is now declined due to: ':                            ' теперь не будет зарегистрирован по причине: ',
        ' is now undeclined.':                                  ' теперь не имеет причины отказа в регистрации.',
        'Note: Server ':                                        'Внимание: сервер ',
        ' is offline, can\'t check it.':                         ' не работает, не могу его проверить.',
        'Wrong language: ':                                     'Неподдерживаемый язык: ',
        'Language set: ':                                       'Установлен язык: ',
        'Wrong command, type /help to get help on commands.':   'Неподдерживаемая команда, напиши /help, чтобы посмотреть, какие команды есть.',
        'This server does not exist. You may try ':             'Такого сервера не существует, есть ',
        'Checking...':                                          'Проверяю...',
        'Server ':                                              'Сервер ',
        ' statistics:':                                         ' сообщает следующую статистику:',
        'Uptime:':                                              'Аптайм:',
        'Load:':                                                'Загрузка:',
        ' can\'t run mpstat':                                   ' не сумел запустить mpstat',
        ' can\'t run uptime':                                   ' не сумел запустить uptime',
        ' is offline.':                                         ' не работает.',
        'You must specify a reason.':                           'Нужно указать причину.',
        'Now specify your language (' + '/'.join(langs) + '):': 'Задайте нужный язык (' + '/'.join(langs) + '):',
        'Now specify server name to query:':                    'Какой сервер опрашиваем?',
        'Now specify username to query:':                       'Про какого пользователя узнаём?',
        'Contact ':                                             'По любым вопросам пиши ',
        ' for further info.':                                   ''
    }
}

def _(l, string):
    if l in l10n.keys():
        if string in l10n[l].keys():
            return l10n[l][string]
    return string
