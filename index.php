<?php

require('config.php');

$inline_css = '<style type="text/css">
.vert-container {
    display: table;
    position: absolute;
    height: 100%;
    width: 100%;
}

.vert-block {
    display: table-cell;
    vertical-align: middle;
}

.success {
    --card-back-color: #dfffdf;
}

.text-justified {
    text-align: justify;
}

.text-centered {
    text-align: center;
}

.text-right {
    text-align: right;
}

.text-green {
    color: green;
}

.text-yellow {
    color: orange;
}

.text-red {
    color: red;
}

.aligned-block {
    display: inline-block;
}

.hidden {
    display: none;
}
</style>';

if(isset($_GET["chat_id"]))
{
        $chat_id = preg_replace("/^@/u", "", $_GET["chat_id"]);
        $ch = curl_init("https://t.me/" . urlencode($chat_id));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        $result = curl_exec($ch);
        if(curl_errno($ch))
        {
            http_response_code(503);
        }
        elseif(empty($result))
        {
            header('Content-type: text/plain');
            print('incorrect');
        }
        else
        {
            $dom = new DomDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($result);
            $finder = new DomXPath($dom);
            $attr = $finder->query("//*[contains(@class, 'tgme_page_extra')]");
            header('Content-type: text/plain');
            if($attr != false && $attr->count() > 0)
            {
                print('exist');
            }
            else
            {
                print('missing');
            }
        }
        curl_close($ch);
}
elseif(isset($_POST["uptime"]))
{
    if(!array_key_exists($_POST["server"], $pubkeys))
    {
        http_response_code(400);
        print("No such server\n");
        die();
    }
    set_include_path('phpseclib');
    include('Crypt/RSA.php');
    $rsa = new Crypt_RSA();
    $rsa->loadKey($pubkeys[$_POST["server"]]);
    $rsa->setHash($_POST["hashtype"]);
    $rsa->setSignatureMode(CRYPT_RSA_SIGNATURE_PKCS1);
    if($rsa->verify($_POST["uptime"], base64_decode( $_POST["sig"])))
    {
        if ($server_db_host != "" && $server_db_user != "" && $server_db_password != "" && $server_db_name != "")
        {
            $mysqli = mysqli_connect($server_db_host, $server_db_user, $server_db_password, $server_db_name);
            if ($mysqli != false)
            {
                mysqli_set_charset($mysqli, "utf8mb4");
                if(mysqli_fetch_array(mysqli_query($mysqli, "SHOW TABLES LIKE '%UPTIME%';")) == NULL)
                {
                    mysqli_query($mysqli, "CREATE TABLE `UPTIME` ( `HOST` VARCHAR(255) NOT NULL , `UPTIME` VARCHAR(255) , `TIMESTAMP` INT(255) ) ENGINE = MyISAM;");
                }
                if(mysqli_fetch_array(mysqli_query($mysqli, "SELECT `UPTIME` FROM `UPTIME` WHERE `HOST` = '" . mysqli_real_escape_string($mysqli, $_POST["server"]) . "';")) == NULL)
                {
                    mysqli_query($mysqli, "INSERT INTO `UPTIME` (`HOST`, `UPTIME`, `TIMESTAMP`) VALUES ('" . mysqli_real_escape_string($mysqli, $_POST["server"]) . "', '" . mysqli_real_escape_string($mysqli, base64_decode($_POST["uptime"])) . "', " . time() . ");");
                }
                else
                {
                    mysqli_query($mysqli, "UPDATE `UPTIME` SET `UPTIME` = '" . mysqli_real_escape_string($mysqli, base64_decode($_POST["uptime"])) . "', `TIMESTAMP` = " . time() . " WHERE `HOST` = '" . mysqli_real_escape_string($mysqli, $_POST["server"]) . "';");
                }
            }
        }
        print("Uptime" . base64_decode($_POST["uptime"]) . "Accepted for server " . $_POST["server"] . "\n");
    }
    else
    {
        http_response_code(406);
        print("Bad signature\n");
    }
}
elseif(isset($_POST["lang"]))
{
    if ($_POST["lang"] == "ru")
    {
        $body_hdr = "Ваш запрос отправлен. Когда он будет обработан, ";
        $disc_hdr = "на <a href=\"" . $discord . "\">Discord-сервер</a> придёт подтверждение и детали подключения";
        $tele_hdr = "в телеграм-аккаунт ";
        $tele_end = " и " . $disc_hdr;
        $info_msg = "обращайтесь в телеграм <a href=\"https://t.me/" . $tg_conf. "\">@" . $tg_conf. "</a>, или на <a href=\"" . $discord . "\">Discord-сервер</a>.";
        $body_end = ". Если возникнут вопросы, " . $info_msg;
        $port_hdr = " и можно будет получить доступ к этим серверам, зайдя по ssh на хост " . $gw_host . ", на порты: ";
        $sngl_hdr = " и можно будет получить доступ к cерверу, зайдя по ssh на хост " . $gw_host . ", порт ";
        $capt_err = "Проверка ReCAPTCHA не выполнена. Вернитесь на предыдущую страницу и попробуйте ещё раз.";
        $none_err = "Вы должны указать хотя бы один сервер. Вернитесь на предыдущую страницу и попробуйте ещё раз.";
        $user_err = "Пользователя с таким именем создать нельзя. Вернитесь на предыдущую страницу и попробуйте ещё раз.";
        $curl_err = "Ошибка при посылке запроса: ";
        $exno_err = "Пользователь " . $_POST["username"] . " уже зарегистрирован и не оставил информации для связи. Такого пользователя создать или изменить автоматически нельзя. Для его изменения " . $info_msg;
        $extg_err = "Пользователь " . $_POST["username"] . " уже зарегистрирован и оставил другой телеграм-аккаунт для связи: ";
        $extg_end = ". Заполните форму, указав этот аккаунт, или, если это невозможно, " . $info_msg;
        $dups_err = "Вы уже зарегистрированы на ";
        $dups_end = ". Вернитесь и снимите лишние галочки. Если вам нужно обновить публичный ключ, " . $info_msg;
    }
    else
    {
        $body_hdr = "Your request has been sent. Once it is approved, ";
        $disc_hdr = "you may check <a href=\"" . $discord . "\">Discord server</a>: there you will have connection details. Still ";
        $tele_hdr = "check telegram ";
        $tele_end = " or <a href=\"" . $discord . "\">Discord server</a>: there you will have connection details";
        $info_msg = "in telegram <a href=\"https://t.me/" . $tg_conf. "\">@" . $tg_conf. "</a>, or on <a href=\"" . $discord . "\">Discord server</a>.";
        $body_end = ". If you have any questions, feel free to ask them " . $info_msg;
        $port_hdr = "you will be able to connect to these servers using ssh to host " . $gw_host . ", ports: ";
        $sngl_hdr = "you will be able to connect to server using ssh to host " . $gw_host . ", port ";
        $capt_err = "ReCAPTCHA check failed, return to previous page and try again.";
        $none_err = "You should specify at least one server. Return to previous page and try again.";
        $user_err = "This username is not allowed, return to previous page and try again.";
        $curl_err = "Error sending request: ";
        $exno_err = "User " . $_POST["username"] . " already exists, and did not left a contact info. Cannot add new server to this account. If it's you, ask for manual correction " . $info_msg;
        $extg_err = "User " . $_POST["username"] . " already exists. If it's you who have created this account before, please specify the same telegram account you used to register: ";
        $extg_end = ", or ask for changing it " . $info_msg;
        $dups_err = "You're already registered on ";
        $dups_end = ". Please go back and deselect it. If you need to update the public key, please ask for this " . $info_msg;

    }

    $error = true;
    $color = "error";
    if (in_array($_POST["username"], $banned_users))
    {
        $body = $user_err;
    }
    elseif (!empty($_POST["g-recaptcha-response"]))
    {
        $body = $capt_err;
        $out = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=" . RECAPTCHA_SECRET_KEY . "&response=" . $_POST["g-recaptcha-response"]);
        $out = json_decode($out);
        if ($out->success == true)
        {
            $error = false;
        }
    }

    if (!$error)
    {
        $lastuid = 0;
        $servers = [];
        $tguser = $_POST["telegram"];
        if(!empty($tguser) && $tguser[0] != "@") $tguser = "@" . $tguser;
        foreach (array_keys($ports) as $server)
        {
            if(isset($_POST["server_" . strtolower($server) ])) $servers[] = $server;
        }
        if(!$servers)
        {
            $error = true;
            $body = $none_err;
        }

        if (!$error && $server_db_host != "" && $server_db_user != "" && $server_db_password != "" && $server_db_name != "")
        {
            $mysqli = mysqli_connect($server_db_host, $server_db_user, $server_db_password, $server_db_name);
            if ($mysqli != false)
            {
                mysqli_set_charset($mysqli, "utf8mb4");

                if(mysqli_fetch_array(mysqli_query($mysqli, "SHOW TABLES LIKE '%USERS%';")) == NULL)
                {
                    mysqli_query($mysqli, "CREATE TABLE `USERS` ( `UID` VARCHAR(255) NOT NULL , `NAME` VARCHAR(255) NOT NULL, `TELEGRAM` VARCHAR(255) ) ENGINE = MyISAM;");
                }
                if(mysqli_fetch_array(mysqli_query($mysqli, "SHOW TABLES LIKE '%SERVERS%';")) == NULL)
                {
                    mysqli_query($mysqli, "CREATE TABLE `SERVERS` ( `UID` VARCHAR(255) NOT NULL , `SERVER` VARCHAR(255) ) ENGINE = MyISAM;");
                }
                $uids = mysqli_fetch_array(mysqli_query($mysqli, "SELECT * FROM `USERS` WHERE `NAME` = '" . mysqli_real_escape_string($mysqli, $_POST["username"]) . "';"));
                if ($uids == NULL)
                {
                    if(mysqli_fetch_array(mysqli_query($mysqli, "SHOW TABLES LIKE '%PARAMS%';")) == NULL)
                    {
                        mysqli_query($mysqli, "CREATE TABLE `PARAMS` ( `NAME` VARCHAR(255) NOT NULL , `VALUE` VARCHAR(255) NOT NULL ) ENGINE = MyISAM;");
                    }
                    if(mysqli_fetch_array(mysqli_query($mysqli, "SELECT `VALUE` FROM `PARAMS` WHERE `NAME` = 'LASTUID';")) == NULL)
                    {
                        mysqli_query($mysqli, "INSERT INTO `PARAMS` (`NAME`, `VALUE`) VALUES ('LASTUID', '" . $default_lastuid . "');");
                        $lastuid = $default_lastuid;
                    }
                    else
                    {
                        $lastuid = mysqli_fetch_array(mysqli_query($mysqli, "SELECT `VALUE` FROM `PARAMS` WHERE `NAME` = 'LASTUID';"))["VALUE"];
                    }
                    mysqli_query($mysqli, "UPDATE `PARAMS` SET `VALUE` = '" . ($lastuid + 1) . "' WHERE `NAME` = 'LASTUID';");
                    mysqli_query($mysqli, "INSERT INTO `USERS` (`UID`, `NAME`, `TELEGRAM`) VALUES ('" . $lastuid . "', '" . $_POST["username"] . "', '" . $tguser . "');");

                    foreach ($servers as $server)
                    {
                        mysqli_query($mysqli, "INSERT INTO `SERVERS` (`UID`, `SERVER`) VALUES ('" . $lastuid . "', '" . strtolower($server) . "');");
                    }
                }
                else
                {
                    $lastuid = $uids["UID"];
                    if($uids["TELEGRAM"] == "")
                    {
                        $error = true;
                        $body = $exno_err;
                    }
                    elseif ($uids["TELEGRAM"] != $tguser)
                    {
                        $error = true;
                        $body = $extg_err . $uids["TELEGRAM"] . $extg_end;
                    }
                    else
                    {
                        $serv_regs = mysqli_fetch_all(mysqli_query($mysqli, "SELECT `SERVER` FROM `SERVERS` WHERE `UID` = '" . $lastuid . "';"));
                        $serv_dups = [];
                        foreach($serv_regs as $serv_reg)
                        {
                            $serv_dup = array_search($serv_reg[0], array_map('strtolower', $servers));
                            if($serv_dup !== false) $serv_dups[] = $servers[$serv_dup];
                        }
                        if($serv_dups)
                        {
                            $error = true;
                            $body = $dups_err . implode(", ", $serv_dups) . $dups_end;
                        }
                        else
                        {
                            foreach ($servers as $server)
                            {
                                mysqli_query($mysqli, "INSERT INTO `SERVERS` (`UID`, `SERVER`) VALUES ('" . $lastuid . "', '" . strtolower($server) . "');");
                            }
                        }
                    }
                }

            }
        }

        if (!$error)
        {
            $message = "newu". ($lastuid == 0 ? "" : " -u " . $lastuid) . " --lang " . $_POST["lang"] . (count($servers) < 2 ? "" : " -s") . " " . $_POST["username"] . " " . $_POST["publickey"] . "\nServers: " . implode(", ", $servers) . "\nTelegram account: " . $tguser;
            $ch = curl_init("https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage?chat_id=" . TELEGRAM_CHATID . "&text=" . urlencode($message));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
            curl_exec($ch);
            if($errno = curl_errno($ch))
            {
                $body = $curl_err . curl_strerror($errno) . " " . curl_error($ch);
            }
            else
            {
                $color = "success";
                if(!empty($_POST["telegram"]))
                {
                    $body = $body_hdr . $tele_hdr . $_POST["telegram"] . $tele_end . $body_end;
                }
                else
                {
                    if (count($servers) > 1)
                    {
                        $portsarr = [];
                        foreach ($servers as $server) $portsarr[] = $server . " &mdash; " . $ports[$server];
                        $portslist = $port_hdr . implode(", ", $portsarr);
                    }
                    else
                    {
                        $portslist = $sngl_hdr . $ports[$servers[0]];
                    }
                    $body = $body_hdr . $disc_hdr . $portslist . $body_end;
                }
            }
            curl_close($ch);
        }
    }
    print('<!DOCTYPE html>
<html>
    <head>
        <title>' . $page_title . '</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/mini.css/3.0.1/mini-default.min.css" />
' . $inline_css . '
    </head>
    <body>
        <div class="col-sm-offset-1 col-sm-10 col-lg-offset-3 col-lg-6 vert-container">
            <div class="vert-block">
                <div class="card fluid ' . $color . '">
                    <div class="section double-padded text-justified">
                        ' . $body . '
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>');
}
else
{
    if ($server_db_host != "" && $server_db_user != "" && $server_db_password != "" && $server_db_name != "")
    {
        $mysqli = mysqli_connect($server_db_host, $server_db_user, $server_db_password, $server_db_name);
        if ($mysqli != false)
        {
            $uptimes = mysqli_fetch_all(mysqli_query($mysqli, "SELECT * FROM `UPTIME`;"), MYSQLI_ASSOC);
        }
    }

    function gen_labels($server, $labels)
    {
        global $uptimes;
        $span = '';
        if (is_array($uptimes))
        {
            foreach ($uptimes as $uptime)
            {
                if (is_array($uptime) && array_key_exists("HOST", $uptime) && $uptime["HOST"] == $server)
                {
                    $color = 'text-green';
                    $text = str_replace(array("\r", "\n"), '', $uptime["UPTIME"]);
                    if (time() > $uptime["TIMESTAMP"] + 600)
                    {
                        $color = 'text-yellow';
                    }
                    if (time() > $uptime["TIMESTAMP"] + 1800)
                    {
                        $color = 'text-red';
                        $text = '<span class=text-offline></span>';
                    }
                    $span = '<span class=' . $color . '> - ' . $text . '</span>';
                }
            }
        }
        return ('                $("#label_server_' . strtolower($server) . '").html("' . $labels[$server] . $span . '");');
    }

    function gen_checkboxes($server, $default_servers)
    {
        return ('                  <div>
                        <input id="server_' . strtolower($server) . '" name="server_' . strtolower($server) . '" type="checkbox"' . (in_array($server, $default_servers) ? ' checked="true"' : '') . '>
                        <label id="label_server_' . strtolower($server) . '" for="server_' . strtolower($server) . '"></label>
                        </div>');
    }

    print('<!DOCTYPE html>
<html>
    <head>
        <title>' . $page_title . '</title>
        <script src="https://www.google.com/recaptcha/api.js"></script>
        <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/mini.css/3.0.1/mini-default.min.css" />
' . $inline_css . '
    </head>
    <body>
        <div class="col-sm-offset-1 col-sm-10 col-lg-offset-3 col-lg-6">
            <div class="text-right"><a id="lang_en" href="." onclick="localize_en();" >ENG</a> | <a id="lang_ru" href="." onclick="localize_ru();" >РУС</a></div>
            <hr>
            <div class="card fluid"><div id="manual" class="section double-padded text-justified"></div></div>
            <div id="error" class="card fluid hidden"><div id="errorsec" class="section text-centered"></div></div>
            <form class="card fluid success" method="post" action="#" id="form">
                <input id="lang" name="lang" type="hidden">
                <label id="label_username" for="username"></label>
                <input id="username" name="username" type="text" placeholder="username">
                <div id="userror" class="card fluid hidden"><div id="userrorsec" class="section text-centered"></div></div>
                <label id="label_publickey" for="publickey"></label>
                <textarea id="publickey" name="publickey" placeholder="ssh-rsa AAAAB3N...2345678== username@hostname"></textarea>
                <label id="label_telegram" for="telegram"></label>
                <input id="telegram" name="telegram" type="text" placeholder="@TelegramUser">
                <div id="tgerror" class="card fluid hidden"><div id="tgerrorsec" class="section text-centered"></div></div>
                <fieldset id="servers">
                    <legend id="label_servers"></legend>
' . implode("\n", array_map(function($i) use ($default_servers) { return (gen_checkboxes($i, $default_servers)); }, array_keys($ports))) . '
                </fieldset>
                <div class="text-centered">
                    <div class="g-recaptcha aligned-block" data-sitekey="' . RECAPTCHA_SITE_KEY . '"></div>
                </div>
                <button id="submitreq" class="primary" type="submit"></button>
            </form>
            <div class="hidden" id="errmsg_telegram"></div>
            <div class="hidden" id="errmsg_tg_fail"></div>
            <div class="hidden" id="errmsg_recaptcha"></div>
            <div class="hidden" id="errmsg_username"></div>
            <div class="hidden" id="errmsg_banneduser"></div>
            <div class="hidden" id="errmsg_publickey"></div>
            <div class="hidden" id="errmsg_pkeytype"></div>
            <div class="hidden" id="errmsg_noservers"></div>
            <div class="hidden" id="errmsg_filesize"></div>
        </div>
        <script>
            $(document).ready(function()
            {
                var lang = navigator.language || navigator.userLanguage;
                if (lang == "ru-RU") localize_ru(); else localize_en();
            });

            $("html").on("dragover", function(event)
            {
                event.preventDefault();
                event.stopPropagation();
            });

            $("html").on("dragleave", function(event)
            {
                event.preventDefault();
                event.stopPropagation();
            });

            $("html").on("drop", function(event)
            {
                event.preventDefault();
                event.stopPropagation();
            });

            function errmsg(selector)
            {
                $("#errorsec").text($(selector).text());
                $("#error").addClass("error");
                $("#error").removeClass("hidden");
                grecaptcha.reset();
                return false;
            }

            $("#publickey").on("drop", function(event)
            {
                if(event.originalEvent.dataTransfer.files[0].size > 65536)
                {
                    return errmsg("#errmsg_filesize");
                }
                var reader = new FileReader();
                reader.onload = function(event) { $("#publickey").val(event.target.result); };
                reader.readAsText(event.originalEvent.dataTransfer.files[0], "UTF-8");
            });

            $("#username").blur(function()
            {
                if(!($("#username").prop("value").match("^[a-z][a-z0-9_]{2,15}$")))
                {
                        $("#userrorsec").text($("#errmsg_username").text());
                        $("#userror").addClass("error");
                        $("#userror").removeClass("hidden");
                }
                else if(["' . implode("\", \"", $banned_users) . '"].includes($("#username").prop("value")))
                {
                        $("#userrorsec").text($("#errmsg_banneduser").text());
                        $("#userror").addClass("error");
                        $("#userror").removeClass("hidden");
                }
                else
                {
                    $("#userror").addClass("hidden");
                }
            });

            $("#telegram").blur(function()
            {
                if($("#telegram").val() == "")
                {
                    $("#tgerror").addClass("hidden");
                    return;
                }
                $.ajax(
                {
                    url: document.location.href.replace(/#.*/,"") + "?chat_id=" + $("#telegram").val(),
                    timeout: 5000,
                    type: "GET"
                }).done((data) =>
                {
                    if(data == "exist")
                    {
                        $("#tgerror").addClass("hidden");
                    }
                    else
                    {
                        $("#tgerrorsec").text($("#errmsg_telegram").text());
                        $("#tgerror").removeClass("warning");
                        $("#tgerror").addClass("error");
                        $("#tgerror").removeClass("hidden");
                    }
                }).fail(() =>
                {
                    $("#tgerrorsec").text($("#errmsg_tg_fail").text());
                    $("#tgerror").removeClass("error");
                    $("#tgerror").addClass("warning");
                    $("#tgerror").removeClass("hidden");
                });
            });

            $("#form").submit(function()
            {
                $("#error").addClass("hidden");

                if(!($("#username").prop("value").match("^[a-z][a-z0-9_]{2,15}$")))
                {
                    return errmsg("#errmsg_username");
                }

                if(["' . implode("\", \"", $banned_users) . '"].includes($("#username").prop("value")))
                {
                    return errmsg("#errmsg_banneduser");
                }

                if(!($("#publickey").val().match("^[a-z0-9-]+ [0-9a-zA-Z\+\/=]+( [^ ]*)?$")))
                {
                    return errmsg("#errmsg_publickey");
                }

                if (!["' . implode("\", \"", $pubkey_types) . '"].includes($("#publickey").val().split(" ")[0]))
                {
                    return errmsg("#errmsg_pkeytype");
                }

                var checked = false; p.children("div").children("input").toArray().forEach((q) =>
                {
                    if (q.checked) checked = true;
                });

                if(!checked)
                {
                    return errmsg("#errmsg_noservers");
                }

                var response = grecaptcha.getResponse();

                if(response.length == 0)
                {
                    return errmsg("#errmsg_recaptcha");
                }
            });

            function localize_ru()
            {
                $("#lang").prop("value", "ru");
                $("#manual").html("<p>Для получения доступа к машинам на базе процессоров &laquo;Эльбрус&raquo; вам надо придумать имя пользователя " +
                    "(3-32 латинских букв в нижнем регистре, цифр, знаков подчёркивания, первый символ - буква) и <a href=\"https://losst.ru/avtorizatsiya-po-klyuchu-ssh\">сгенерировать SSH-ключи</a>, " +
                    "чтобы потом по ним авторизовываться (если у вас уже есть существующая ключевая пара, то можно использовать её), после чего указать имя пользователя " +
                    "в форме ниже, вставить <strong>публичный</strong> ключ (в формате OpenSSH .pub, т.е. одной строчкой) в соответствующее поле (также можно перетащить туда файл с ним), выбрать сервера, " +
                    "к которым запрашивается доступ, нажать галочку рекапчи, опционально ввести имя аккаунта в телеграме, на который будет отправлено оповещение о создании аккаунта и отправить запрос.</p>" +
                    "<p>После того, как запрос послан, можно проверить его статус, написав телеграм-боту <a href=\"https://t.me/' . $tg_bot. '\">@' . $tg_bot. '</a>.</p>" +
                    "<p>Также оповещения о новых пользователях можно увидеть (а также обсудить всё, касающееся серверов) на <a href=\"' . $discord. '\">Discord-сервере</a>.</p>" +
                    "<p>Время обработки запроса &mdash; от нескольких минут до нескольких дней.</p>" +
                    "<p>Сервис работает в режиме &laquo;как есть&raquo;. Доступность серверов 100% не гарантируется. По любым вопросам обращайтесь в телеграм <a href=\"https://t.me/' . $tg_conf. '\">@' . $tg_conf. '</a>.</p>" +
                    "<p>Если при подключении возникает ошибка \"no mutual signature algorithm\", попробуйте добавить \"PubkeyAcceptedKeyTypes +ssh-rsa\" в конфиг клиента (<a href=\"https://www.reddit.com/r/linuxquestions/comments/qgmnnh/comment/hi785p9/\">см. здесь</a>).</p>" +
                    "<p>Если PuTTY не подключается и выдаёт ошибку типа \"Couldn\'t agree on host key algorithm\", необходимо <a href=\"https://sysadmins.online/threads/17881/\">его обновить</a>.</p>")
                $("#label_username").text("Имя пользователя:")
                $("#label_publickey").text("Публичный ключ:")
                $("#label_telegram").text("Аккаунт в телеграме (необязательно) для сообщения о результате (не ID и не телефон, т.е. @user, а не 1234567890 или +79012345678):")
                $("#telegram").prop("title", "Внимание: вы не сможете получить оповещение о готовности аккаунта, если не укажете здесь контакт для связи")
                $("#username").prop("title", "Разрешены латинские строчные буквы; вторым и далее символом также цифры и знак подчёркивания")
                $("#publickey").prop("title", "Поддерживается перетаскивание .pub-файла. Поддерживаются ключи ' . implode(", ", $pubkey_types) . '")
                $("#submitreq").text("Отправить запрос");
                $("#errmsg_recaptcha").html("Отметьте галочку &laquo;Я не робот&raquo;.");
                $("#errmsg_noservers").text("Выберите хотя бы один сервер.");
                $("#errmsg_filesize").text("Открытый ключ должен быть не более 16 кБ размером.");
                $("#errmsg_username").text("Имя пользователя должно быть от 3 до 16 символов, состоять из латинских букв, цифр и знаков подчёркивания и начинаться с буквы.");
                $("#errmsg_telegram").text("Такой пользователь Telegram не обнаружен. Если вы отправите форму с таким именем пользователя, вероятно, вы не получите подтверждения о создании аккаунта (впрочем, его можно будет увидеть на Discord-сервере).");
                $("#errmsg_banneduser").text("Пользователя с таким именем создать нельзя.");
                $("#errmsg_tg_fail").text("Невозможно проверить корректность аккаунта Telegram. Если такой аккаунт не существует, то вероятно, вы не получите подтверждения о создании аккаунта (впрочем, его можно будет увидеть на Discord-сервере).");
                $("#errmsg_publickey").text("Открытый ключ должен быть в формате OpenSSH *.pub: сначала тип ключа, потом строка в Base64, потом опционально комментарий.");
                $("#errmsg_pkeytype").text("Открытый ключ должен быть одного из следующих типов: ' . implode(", ", $pubkey_types) . '.");
                $("#label_servers").text("Сервера, на которые запрашивается доступ:");
' . implode("\n", array_map(function($i) use ($labels_ru) { return (gen_labels($i, $labels_ru)); }, array_keys($ports))) . '
                $(".text-offline").text("НЕ РАБОТАЕТ");
            }

            function localize_en()
            {
                $("#lang").prop("value", "en");
                $("#manual").html("<p>To get access to Elbrus CPU based servers, you should choose a user name " +
                    "(3 to 32 latin lowercase letters, numbers, underscores, beginning with a letter), and <a href=\"https://www.ssh.com/academy/ssh/key\">generate SSH key pair</a>, " +
                    "to authenticate on these servers (if you already have a key pair, you could probably use it), then you should specify your user name " +
                    "in the form below, put yout <strong>public</strong> key (OpenSSH .pub format, i.e. a single line) in the corresponding field (or drag and drop .pub file there), choose servers you want " +
                    "to have access to, perform ReCAPTCHA challenge, optionally you can specify your Telegram username to contact you and send connection details to you, and submit the request.</p>" +
                    "<p>Once request is sent, you may check its state by contacting telegram bot <a href=\"https://t.me/' . $tg_bot. '\">@' . $tg_bot. '</a>.</p>" +
                    "<p>Also you can check for new user notification (and discuss everything related) on the <a href=\"' . $discord. '\">Discord server</a>.</p>" +
                    "<p>The time it takes to approve a request ranges from a few minutes to several days.</p>" +
                    "<p>Service is offered as is. 100% availability is not guaranteed. For any questions, contact telegram <a href=\"https://t.me/' . $tg_conf. '\">@' . $tg_conf. '</a>.</p>" +
                    "<p>Note: if you have a connection error like \"no mutual signature algorithm\" try adding \"PubkeyAcceptedKeyTypes +ssh-rsa\" in client config. <a href=\"https://www.reddit.com/r/linuxquestions/comments/qgmnnh/comment/hi785p9/\">More info here</a>.</p>" +
                    "<p>If you have PuTTY, and get an error like \"Couldn\'t agree on host key algorithm\", just <a href=\"https://sysadmins.online/threads/17881/\">update your client</a>.</p>")
                $("#label_username").text("Username:")
                $("#label_publickey").text("Public key:")
                $("#label_telegram").text("Telegram username (optional) to contact (not ID or phone, e.g. @user, not 1234567890 or +79012345678):")
                $("#telegram").prop("title", "Note: you won\'t be able to receive a confirmation, if you don\'t specify a contact here")
                $("#username").prop("title", "Lowercase latin letters, numbers, underscore are allowed (fist character must be a letter)")
                $("#publickey").prop("title", "You may either paste key here or drag and drop .pub file. Supported key types: ' . implode(", ", $pubkey_types) . '")
                $("#submitreq").text("Send a request");
                $("#errmsg_recaptcha").html("Perform ReCAPTCHA check.");
                $("#errmsg_noservers").text("Select at least one server.");
                $("#errmsg_filesize").text("Public key size must be less than 16 kB.");
                $("#errmsg_username").text("Username should be 3 to 16 characters, consisting of latin letters, numbers and underscores, starting with a letter.");
                $("#errmsg_telegram").text("Such Telegram user does not exist. If you sumbit the form with that field filled like that, you highly possibly won\'t receive a confirmation that your account is created unless you check the Discord server.");
                $("#errmsg_banneduser").text("This username is not allowed.");
                $("#errmsg_tg_fail").text("Can\'t check if Telegram user exists. If it does not exist, you highly possibly won\'t receive a confirmation that your account is created unless you check the Discord server.");
                $("#errmsg_publickey").text("Public key should be in OpenSSH *.pub format: key type, then Base64 key value, then optionally a comment.");
                $("#errmsg_pkeytype").text("Public key should be of one of the folloing types: ' . implode(", ", $pubkey_types) . '.");
                $("#label_servers").text("Servers you want access to:");
' . implode("\n", array_map(function($i) use ($labels_en) { return (gen_labels($i, $labels_en)); }, array_keys($ports))) . '
                $(".text-offline").text("OFFLINE");
            }
        </script>
    </body>
</html>');
}
?>
