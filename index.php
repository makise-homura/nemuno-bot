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

.aligned-block {
    display: inline-block;
}

.hidden {
    display: none;
}
</style>';

if(isset($_POST["lang"]))
{
    if ($_POST["lang"] == "ru")
    {
        $body_hdr = "Ваш запрос отправлен. Когда он будет обработан, ";
        $tele_hdr = "в телеграм-аккаунт ";
        $tele_end = " придёт подтверждение и детали подключения";
        $body_end = ". Если возникнут вопросы, обращайтесь в телеграм <a href=\"https://t.me/" . $tg_conf. "\">@" . $tg_conf. "</a>.";
        $port_hdr = "можно будет получить доступ к этим серверам, зайдя по ssh на хост " . $gw_host . ", на порты: ";
        $sngl_hdr = "можно будет получить доступ к cерверу, зайдя по ssh на хост " . $gw_host . ", порт ";
        $capt_err = "Проверка ReCAPTCHA не выполнена. Вернитесь на предыдущую страницу и попробуйте ещё раз.";
        $curl_err = "Ошибка при посылке запроса: ";
    }
    else
    {
        $body_hdr = "Your request has been sent. Once it is approved, ";
        $tele_hdr = "check telegram ";
        $tele_end = ": there you will have connection details";
        $body_end = ". If you have any questions, feel free to ask them in telegram <a href=\"https://t.me/" . $tg_conf. "\">@" . $tg_conf. "</a>.";
        $port_hdr = "you will be able to connect to these servers using ssh to host " . $gw_host . ", ports: ";
        $sngl_hdr = "you will be able to connect to server using ssh to host " . $gw_host . ", port ";
        $capt_err = "ReCAPTCHA check failed, return to previous page and try again.";
        $curl_err = "Error sending request: ";
    }

    $error = true;
    if (!empty($_POST["g-recaptcha-response"]))
    {
        $out = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret=" . RECAPTCHA_SECRET_KEY . "&response=" . $_POST["g-recaptcha-response"]);
        $out = json_decode($out);
        if ($out->success == true)
        {
            $error = false;
        }
    }
    if ($error)
    {
        $color = "error";
        $body = $capt_err;
    }
    else
    {
        $lastuid = 0;

        if ($server_db_host != "" && $server_db_user != "" && $server_db_password != "" && $server_db_name != "")
        {
            $mysqli = mysqli_connect($server_db_host, $server_db_user, $server_db_password, $server_db_name);
            if ($mysqli != false)
            {
                $lastuid = mysqli_fetch_array(mysqli_query($mysqli, "SELECT `VALUE` FROM `PARAMS` WHERE `NAME` = 'LASTUID';"))["VALUE"];
                mysqli_query($mysqli, "UPDATE `PARAMS` SET `VALUE` = '" . ($lastuid + 1) . "' WHERE `NAME` = 'LASTUID';");
            }
        }

        $servers = [];
        foreach (array_keys($ports) as $server)
        {
            if(isset($_POST["server_" . strtolower($server) ])) $servers[] = $server;
        }
        $message = "newu". ($lastuid == 0 ? "" : " -u " . $lastuid) . " --lang " . $_POST["lang"] . (count($servers) < 2 ? "" : " -s") . " " . $_POST["username"] . " " . $_POST["publickey"] . "\nServers: " . implode(", ", $servers) . "\nTelegram account: " . $_POST["telegram"];
        $ch = curl_init("https://api.telegram.org/bot" . TELEGRAM_TOKEN . "/sendMessage?chat_id=" . TELEGRAM_CHATID . "&text=" . urlencode($message));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FAILONERROR, true);
        curl_exec($ch);
        if($errno = curl_errno($ch))
        {
            $color = "error";
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
                $body = $body_hdr . $portslist . $body_end;
            }
        }
        curl_close($ch);
    }
    print('<!DOCTYPE html>
<html>
    <head>
        <title>' . $page_title . '</title>
        <link rel="stylesheet" href="https://gitcdn.link/cdn/Chalarangelo/mini.css/e849238d198c032c9d3fa84ccadf59ea7f0ad06c/dist/mini-default.min.css" />
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
    function gen_labels($server, $labels)
    {
        return ('                $("#label_server_' . strtolower($server) . '").html("' . $labels[$server] . '");');
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
        <link rel="stylesheet" href="https://gitcdn.link/cdn/Chalarangelo/mini.css/e849238d198c032c9d3fa84ccadf59ea7f0ad06c/dist/mini-default.min.css" />
' . $inline_css . '
    </head>
    <body>
        <div class="col-sm-offset-1 col-sm-10 col-lg-offset-3 col-lg-6">
            <div class="text-right"><a id="lang_en" href="#" onclick="localize_en();" >ENG</a> | <a id="lang_ru" href="#" onclick="localize_ru();" >РУС</a></div>
            <hr>
            <div class="card fluid"><div id="manual" class="section double-padded text-justified"></div></div>
            <div id="error" class="card fluid hidden"><div id="errorsec" class="section text-centered"></div></div>
            <form class="card fluid success" method="post" action="#" id="form">
                <input id="lang" name="lang" type="hidden">
                <label id="label_username" for="username"></label>
                <input id="username" name="username" type="text" placeholder="username">
                <label id="label_publickey" for="publickey"></label>
                <textarea id="publickey" name="publickey" placeholder="ssh-rsa AAAAB3N...2345678== username@hostname"></textarea>
                <label id="label_telegram" for="telegram"></label>
                <input id="telegram" name="telegram" type="text" placeholder="@TelegramUser">
                <fieldset id="servers">
                    <legend id="label_servers"></legend>
' . implode("\n", array_map(function($i) use ($default_servers) { return (gen_checkboxes($i, $default_servers)); }, array_keys($ports))) . '
                </fieldset>    
                <div class="text-centered">
                    <div class="g-recaptcha aligned-block" data-sitekey="' . RECAPTCHA_SITE_KEY . '"></div>
                </div>
                <button id="submitreq" class="primary" type="submit"></button>
            </form>
            <div class="hidden" id="errmsg_recaptcha"></div>
            <div class="hidden" id="errmsg_username"></div>
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

            $("#form").submit(function()
            {
                $("#error").addClass("hidden");

                if(!($("#username").prop("value").match("^[a-z][a-z0-9_]{2,15}$")))
                {
                    return errmsg("#errmsg_username");
                }

                if(!($("#publickey").val().match("^[a-z0-9-]+ [0-9a-zA-Z\+\/=]+( [^ ]*)?$")))
                {
                    return errmsg("#errmsg_publickey");
                }

                if (!["' . implode("\", \"", $pubkey_types) . '"].includes($("#publickey").val().split(" ")[0]))
                {
                    return errmsg("#errmsg_pkeytype");
                }

                if(!($("#server_yukari").prop("checked")) && !($("#server_mamizou").prop("checked")) && !($("#server_sumireko").prop("checked")) && !($("#server_raiko").prop("checked")))
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
                    "<p>Время обработки запроса &mdash; от нескольких минут до нескольких дней.</p>" +
                    "<p>Сервис работает в режиме &laquo;как есть&raquo;. Доступность серверов 100% не гарантируется. По любым вопросам обращайтесь в телеграм <a href=\"https://t.me/' . $tg_conf. '\">@' . $tg_conf. '</a>.</p>" +
                    "<p>Если при подключении возникает ошибка \"no mutual signature algorithm\", попробуйте добавить \"PubkeyAcceptedKeyTypes +ssh-rsa\" в конфиг клиента (<a href=\"https://www.reddit.com/r/linuxquestions/comments/qgmnnh/comment/hi785p9/\">см. здесь</a>).</p>" +
                    "<p>Если PuTTY не подключается и выдаёт ошибку типа \"Couldn\'t agree on host key algorithm\", необходимо <a href=\"https://sysadmins.online/threads/17881/\">его обновить</a>.</p>")
                $("#label_username").text("Имя пользователя:")
                $("#label_publickey").text("Публичный ключ:")
                $("#label_telegram").text("Аккаунт в телеграме для сообщения о результате (не ID и не телефон, т.е. @user, а не 1234567890 или +79012345678):")
                $("#username").prop("title", "Разрешены латинские строчные буквы; вторым и далее символом также цифры и знак подчёркивания")
                $("#publickey").prop("title", "Поддерживается перетаскивание .pub-файла. Поддерживаются ключи ' . implode(", ", $pubkey_types) . '")
                $("#submitreq").text("Отправить запрос");
                $("#errmsg_recaptcha").html("Отметьте галочку &laquo;Я не робот&raquo;");
                $("#errmsg_noservers").text("Выберите хотя бы один сервер");
                $("#errmsg_filesize").text("Открытый ключ должен быть не более 16 кБ размером");
                $("#errmsg_username").text("Имя пользователя должно быть от 3 до 16 символов, состоять из латинских букв, цифр и знаков подчёркивания и начинаться с буквы");
                $("#errmsg_publickey").text("Открытый ключ должен быть в формате OpenSSH *.pub: сначала тип ключа, потом строка в Base64, потом опционально комментарий");
                $("#errmsg_pkeytype").text("Открытый ключ должен быть одного из следующих типов: ' . implode(", ", $pubkey_types) . '");
                $("#label_servers").text("Сервера, на которые запрашивается доступ:");
' . implode("\n", array_map(function($i) use ($labels_ru) { return (gen_labels($i, $labels_ru)); }, array_keys($ports))) . '
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
                    "<p>The time it takes to approve a request ranges from a few minutes to several days.</p>" +
                    "<p>Service is offered as is. 100% availability is not guaranteed. For any questions, contact telegram <a href=\"https://t.me/' . $tg_conf. '\">@' . $tg_conf. '</a>.</p>" +
                    "<p>Note: if you have a connection error like \"no mutual signature algorithm\" try adding \"PubkeyAcceptedKeyTypes +ssh-rsa\" in client config. <a href=\"https://www.reddit.com/r/linuxquestions/comments/qgmnnh/comment/hi785p9/\">More info here</a>.</p>" +
                    "<p>If you have PuTTY, and get an error like \"Couldn\'t agree on host key algorithm\", just <a href=\"https://sysadmins.online/threads/17881/\">update your client</a>.</p>")
                $("#label_username").text("Username:")
                $("#label_publickey").text("Public key:")
                $("#label_telegram").text("Telegram username to contact (not ID or phone, e.g. @user, not 1234567890 or +79012345678):")
                $("#username").prop("title", "Lowercase latin letters, numbers, underscore are allowed (fist character must be a letter)")
                $("#publickey").prop("title", "You may either paste key here or drag and drop .pub file. Supported key types: ' . implode(", ", $pubkey_types) . '")
                $("#submitreq").text("Send a request");
                $("#errmsg_recaptcha").html("Perform ReCAPTCHA check");
                $("#errmsg_noservers").text("Select at least one server");
                $("#errmsg_filesize").text("Public key size must be less than 16 kB");
                $("#errmsg_username").text("Username should be 3 to 16 characters, consisting of latin letters, numbers and underscores, starting with a letter");
                $("#errmsg_publickey").text("Public key should be in OpenSSH *.pub format: key type, then Base64 key value, then optionally a comment");
                $("#errmsg_pkeytype").text("Public key should be of one of the folloing types: ' . implode(", ", $pubkey_types) . '");
                $("#label_servers").text("Servers you want access to:");
' . implode("\n", array_map(function($i) use ($labels_en) { return (gen_labels($i, $labels_en)); }, array_keys($ports))) . '
            }
        </script>
    </body>
</html>');
}
?>
