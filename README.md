# Contents

* PHP page used to send requests through bot and check if requests are accepted
* Bot used to allow users to know something about the hosts/users
* Host-side script to create user and apply a public key for them

# Prerequisites

* Create a bot through @BotFather, get its token and @-name.
* Create a chat for user support, get its @-name.
* Acquire a domain and register ReCaptcha v2 (not v3!) for it, get its keys (site and secret).
* Use website hosting with PHP (and optionally MySQL, see below) enabled.
* Find out your own telegram chat id, you can do it by sending something to @ShowJsonBot and check `message.chat.id` field. Bot will allow admin commands only from it.

# PHP Page

Consider every server could be accessed by external users using ssh on a certain port on a common server, we'll call it gateway.
Note: the bot may have a different way to access these servers (e.g. directly or through another gateway); it does not matter now.

Rename `config.php.template` to `config.php` and configure it as follows:

* `$page_title`: website page title
* `$tg_conf`: @-name of user support chat (without `@`)
* `$discord`: Invite link to Discord server
* `$tg_bot`: @-name of bot (without `@`)
* `$gw_host`: gateway hostname/IP address
* `$pubkey_types`: types of pubkeys allowed on server
* `RECAPTCHA_SECRET_KEY`: ReCAPTCHA v2 secret key
* `RECAPTCHA_SITE_KEY`: ReCAPTCHA v2 site key
* `TELEGRAM_TOKEN`: bot token
* `TELEGRAM_CHATID`: admin chat id
* `$ports`: dictionary, where server names are keys, and corresponding port numbers on gateway are values
* `$labels_en`: dictionary, where server names are keys, and corresponding labels (in English) on the webpage are values
* `$labels_en`: dictionary, where server names are keys, and corresponding labels (in Russian) on the webpage are values
* `$default_servers`: list of server names that will appear checked on a webpage
* `$default_lastuid`: user ID from which start to create users if server database is just created (default `"12345"`), see below
* `$banned_users`: dictionary of usernames which creation isn't allowed on servers (e.g. system users such as `root`)

Currently only `ru` and `en` languages are supported.

You may want created users to have the same user ID and user group ID on all the servers, starting from some `$default_lastuid` (and UID equal to GID for simplicity).
If so, MySQL should be available to PHP webpage, mysqli module should be enabled for PHP, and you have to create MySQL database on the web server.

In this case, you need to additionally configure this (otherwise, leave these fields blank):

* `$server_db_host`: website MySQL hostname
* `$server_db_user`: website MySQL user name
* `$server_db_password`: website MySQL user password
* `$server_db_name`: website MySQL database name

After you've done all this, upload `config.php` and `index.php` to the server (and make sure `index.php` is the default directory index). Now, for any successful request you will get a message from the bot (see below).

Message will be sent even when the bot itself is not running.

## What if user with that name already exists

If MySQL database is available, PHP page can check for duplicate user requests.
The only allowed ones are for adding new servers to account.
They should have the same telegram account as has been specified before (it shall not be empty; if so, no duplicate request is allowed), and have marked servers which do not have such account already.
For such requests, UID for this user in bot message will be the same as it was in first request.

# User creation script

Upload `newu` onto each target host. Once it is uploaded, it might be updated by running `newu -U`.
Configure `c_homeroot` (where users' home directories reside), `c_host` (gateway hostname/IP), `c_port` (this host's gateway port), and `c_servername` (server name to be displayed) variables in `/etc/newu.conf`.
Also configure `c_userhook_url`, and optionally, `c_userhook_pkeyfile`, and/or `c_userhook_hashtype` to match server configuration.
First variable is URL of your PHP page, second one is path to private SSH key of your host, and last one is openssl default digest algoritm (hint: try `sha256` or `sha1` if unsure).
These three variables let your PHP page know about users being added to certain server, and correctly display this information.

You may also rewrite these variables directly in `newu` script, but this is discouraged because it will be harder to update this script from git repository once it's updated.

Now, when some user applies for access on the website, you will receive the following message to your admin chat:

```
newu --lang ru -u 999 -s cirno ssh-rsa BaKaBaKaBaKaBaKaBaKaBaKaBaKaBaKaBaKaBaKaBaKaBaKaBaKa cirno@mistylake.jp
Servers: Host9 Host999
Telegram account: @CirnoTheStrongest
```

Just do the following:
1. Log in to each of the servers in second line (you must have sudo rights there);
2. Execute command specified in first line there;
3. Copy-paste the last message `newu` printed, and send it to the telegram user in the third line.

Note 1: if there's more than one server in request, server name will be included in each newu output.

Note 2: if user home directory already exists, it will be reused.

What if user did not specify a telegram account? Then skip third part, and use the bot (see below).

## Discord integration (optional)

If you have a Discord server, you may send user creation notifications directly to it.

Just create a webhook for the desired channel, get its link, and add `c_discord_webhook` variable in `/etc/newu.conf` with this link, like this:

```
c_discord_webhook="https://discord.com/api/webhooks/1234567890123456789/thisisthefullpathtodiscordwebhookyoucreatedonyourserverfornotifications"
```

Once you did this, each successful call to `newu` script will send a message to the channel.

Note: it will contain username, server, host and port, and vill be visible to everyone on the server who can read this channel.
If usernames are not to be disclosed, this option is not for you.

Do not forget to chown `/etc/newu.conf` to the user `newu` is to be run under, and chmod it to 600. Otherwise, your Discord webhook URL will be disclosed to server users!

### Avoiding Discord ban in Russia

Download [ByeDPI](https://github.com/hufrea/byedpi), build and install it.

If you have sysvinit-based OS (e.g. OS Elbrus), copy the supplied `ciadpi.rc` file to `/etc/init.d/ciadpi`, and execute `sudo chkconfig ciadpi on && sudo service ciadpi start`.

Add the following line to `/etc/newu.conf`:
```
c_discord_curlparams="--proxy socks5://localhost:1080"
```

If you already use ByeDPI in your network, you might just replace `localhost` with the address of server it's installed on (and don't bother with installing it at the local machine).
Obviously ByeDPI should be open to the network (e.g. not be run with `-i` other than `-i 0.0.0.0`) in this case.

You might consider modifying `PARAMS` in `ciadpi.rc` or `BYEDPI_OPTIONS` in `byedpi.conf` and restarting service if `curl --proxy socks5://localhost:1080 https://discord.com` does not work.

If your system's OpenSSH/GnuTLS does not support TLSv1.3, then probably you won't be able to use locally installed curl with ByeDPI.
In that case, you may use statically built curl that supports it.
To achieve that, specify something like `c_curl_cmd="/opt/curl-static/curl"` in `/etc/newu.conf`.
This curl will be used only for Discord webhook, not for other purposes.

You even can use curl from other machine that is available by ssh. To do this, use (by supplying its path in `c_curl_cmd`) a wrapper script like this (replace `REMOTE_HOST` accordingly):
```
#!/bin/bash
printf "\\\'%q\\\' " "$@" | xargs ssh REMOTE_HOST curl -s
```

# Uptime indication (optional)

You may have uptime indication on the webpage people use to request access.

To do this, you should perform the following actions:

First, on each server, execute:

```
sudo wget https://github.com/makise-homura/nemuno-bot/raw/master/update_uptime.cron -O /etc/cron.d/update_uptime
sudo wget https://github.com/makise-homura/nemuno-bot/raw/master/update_uptime.sh -O /usr/bin/update_uptime.sh
sudo chmod 755 /usr/bin/update_uptime.sh
```

Edit `/usr/bin/update_uptime.sh` to change server name (it must case-sensitively match one of the keys of each dictionary you specified in `config.php`) in `SERVER` variable, and URL of your webpage in `URL` variable.
You may also edit `HASHTYPE` and `PKEYFILE` to match openssl default digest algoritm (hint: try `sha256` or `sha1` if unsure) and path to private SSH key of your host.

You should have `uptime`, `base64`, `openssl`, and `curl` programs, and running `cron` on your host.
You may need to restart `cron` or perform `touch /etc/crontab` for `cron` to recognize newly scheduled task.

Second, get each server's public host SSH key, and specify it in `$pubkeys` dictionary in `config.php` on your web server.
Webpage would accept ONLY uptimes from servers specified in this dictionary, and ONLY if such an uptime report is signed with corresponding host private key.
For now, only SSH RSA keys are known to be supported.

Additionally, you should install [phpseclib](https://sourceforge.net/projects/phpseclib/) to your server (unpack the downloaded ZIP contents into the `phpseclib` subdirectory in your WWW root).

After you did that, your PHP page would accept uptime reports from servers and display them at the main page.
If any server didn't send reports for more than 10 minutes, its uptime will be shown in orange color instead of green.
If it didn't contact the webpage for more than 30 minutes, the server is considered dead, and `OFFLINE` mark is shown instead of last uploaded uptime report.
If server never contacted the webpage, its uptime is not shown (it is considered unconfigured for sending uptime reports due to some valid reason, so this case doesn't count as error).

Note: you may run `update_uptime.sh` manually on any host to check if everything is ok. If so, it will print the uptime sent to the server and tell that it is accepted, like this:

```
Uptime 02:29:06 up 90 days,  8:07,  4 users,  load average: 3,40, 3,71, 3,81
Accepted for server Raiko
```

If it prints something like `Bad signature`, check if keys match (e.g. fingerprint of private host key on server of question matches the public one put into `config.php`), and if `openssl` uses the algorithm specified in `HASHTYPE`.

# Automatic reboot (optional)

You may automatically reboot servers if they fail to be accessed in specified time.

To do this, you need a ssh-enabled BMC on each server, and a machine (may or may not be available from internet) that can access these BMCs and servers (we'll call it *pinger* later).
This was tested on Elbrus servers and [REIMU](https://github.com/makise-homura/openbmc/releases/tag/reimu-1.0.2)-powered BMCs.
Servers may be accessible from pinger directly of via SSH gateway; BMC should be accessible directly (or configured correspondlingly in `ssh_config`).

Proceed as follows:
* Create an unprivileged user on each server, who can execute `true` command by an ssh session.
* Create an user who can issue platform reboot on each BMC.
* Make sure you can log in from pinger to every server and every BMC using public key authentication (e.g. put a private key to pinger, and add a public one to `authorized_keys`, and connect at least once to add both of them to `known_hosts`).
* Make sure pinger has `/usr/bin/ssh`, `/bin/date`, `/usr/bin/curl`, `/usr/bin/realpath`, `/usr/bin/dirname`, `/bin/cat`, and `/bin/bash` with `echo` builtin supported.
* Put `check_avail` and `reset_unavail` into some directory on pinger, and create required files there (see below).
* Put `check_and_reset.cron` as `/etc/cron.d/check_and_reset` onto pinger. Edit paths in this file according to the previous step, and copy the two lines you find there as many times as there are servers. Edit hostnames correspondlingly in each pair of lines.
* Make sure pinger has CRON running and aware of the file above.

The files required for `check_avail` and `reset_unavail` are (for each server named `hostname`):
* `hostname.pk` (optional): a private key to log in to a server named `hostname`. If doesn't exist, `ssh` uses the default key.
* `hostname.port` (optional): SSH port to log in to a server named `hostname`. If doesn't exist, defaults to `22`.
* `hostname.user` (optional): username to log in to a server named `hostname` (the user you created on the server before). If doesn't exist, defaults to `root`.
* `hostname.host` (optional): actual hostname to log in to a server named `hostname`. If doesn't exist, defaults to hostname itself.
* `hostname.bmc`: hostname of BMC of server named `hostname` (will be known as `bmc_hostname` below). Note: this file is required. If doesn't exist, no reboot is issued and error message is written to log file.
* `bmc_hostname.user` (optional): username to log in to a BMC named `bmc_hostname`. If doesn't exist, defaults to `root`.
* `bmc_hostname.pk` (optional): a private key to log in to a BMC named `bmc_hostname`. If doesn't exist, `ssh` uses the default key.
* `bmc_hostname.command` (optional): a command to execute on BMC named `bmc_hostname` is unavailable. If doesn't exist, defaults to `server_reset` (as for REIMU).

The following files are common for all servers:
* `limit` (optional): limit in seconds, after which server is to be rebooted if unavailable for this amount of time. If doesn't exist, defaults to `3600` (an hour).
* `https_proxy` (optional): HTTPS proxy to send webhook message. If doesn't exist, no proxy is used (unless explicitly specified in CRON script).
* `webhook` (optional): Discord webhook to send notification message to (like "Notice: Server ... was unavailable at ..., performed cold reset.") if some server is rebooted due to unavailability. If doesn't exist, no message is sent.

Each time `reset_unavail` is called by CRON for a server `hostname`, it appends a message (like "Server ... is alive at ..." if last check by `check_avail` succeeded, and "Reset server ... at ..." if it was unsuccessful for a specified time and rebooted) to a logfile `hostname.log`.
Timestamp of a last check by `check_avail` is saved into `hostname.timestamp` file.

# Bot (optional)

You will receive messages from the website through the bot even without it running; but if you want users to have some additional info, and you to have some additional control, then let the bot run.

From the host where you will run the bot, make sure you can login (e.g. no key mismatch, unknown host warning, etc.) to every target host using public key authentication.
The way you connect may differ from how external users connect to the same servers.
You should know hostname/IP, port, username, and path to private key for each target host; let call it bot-side parameters `host`, `port`, `user`, and `key` correspondingly.

Then, rename `nemuno_config.py.template` to `nemuno_config.py` and configure it as follows:

* `token`: bot token
* `admin_chatid`: admin chat id
* `dbfile`: database file to store data (will be created in current directory if needed)
* `servers`: dictionary, where where server names are keys, and a dictionary is a value described below.

In `servers`, every subsequent dictionary should consist of:

* `host` (mandatory): SSH host for corresponding server
* `port` (mandatory): SSH port number to connect to
* `user` (mandatory): username on server
* `key` (mandatory): SSH private key filename
* `disabled_algorithms` (optional): value of Paramiko `disabled_algorithms` parameter, e.g. `dict(pubkeys=["rsa-sha2-512", "rsa-sha2-256"])`

Currently only `ru` and `en` languages are supported.
If you want to localize bot to any other language too, edit `nemuno_l10n.py`: add language code to `langs` list, and then another entry to `l10n` dictionary, where key is your language code, and value is translation dictionary.
To form the latter one, just copy any other as an example and alter values correspondingly.

After you've done all this, copy `nemuno_config.py`, `nemuno_bot.py`, and `nemuno_l10n.py` to, say, `/var/lib/nemuno`, create user `nemuno` with the same homedir, chown every file inside to `nemuno`.
Now you're ready to run the bot! Try running `./nemuno_bot.py` and communicate with it through telegram.

If everything's okay, you may let the bot run as a systemd service. This will help it run on boot and restart on failure.

To do this, copy `nemuno.service` to `/etc/systemd/user` and execute `systemctl enable nemuno`.

Prior to this, you may edit this file if you have different `User`, `Group`, or `WorkingDirectory`.
If you have proxy, you may uncomment `Environment` lines and fill them as required.
If you want logging not just to systemd journal, but to specific files, use `StandardOutput` and `StandardError` lines.
Bot will log every incoming and outgoing message to its stdout.

You may learn how to use the bot by sending it the `/help` command.

# What to do if user didn't specify telegram account

Ok, user didn't specify telegram account (or specified the wrong one, despite the warning automatically shown to them if username they enter does not exist). How it will work then?

Let us have user with name `cirno`, and assume she applied to be granted the access through web form. Sooner or later she can reach telegram, and ask the bot:

```
/state cirno
```

So, if you did not process her request, the bot will reply her:

```
User cirno is not added on any online servers yet.
```

But if she did everything ok, and you've added her to some servers, she will see things like this:

```
User cirno is active on following online servers: host9, host999
```

She must know how to connect to these servers: she has been told how when she applied for access (and if she forgot, she has a user support chat link on the webpage).

But what if cirno is a baka and did some stuff that isn't allowed, like, sent wrong public key or her username is already used? You can use `/decline` command (available only for admin chat) like this:

```
/decline cirno You're a baka and don't know how to apply for the access properly
```

And then she'll see in reply for `/state` request:

```
User cirno is not added because of: You're a baka and don't know how to apply for the access properly.
```

So she could re-apply or ask admin to elaborate and/or add her manually. And when she managed to do all the stuff correctly, you may `/undecline` her.
