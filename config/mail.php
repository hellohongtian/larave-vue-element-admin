<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Mail Driver
    |--------------------------------------------------------------------------
    |
    | Laravel supports both SMTP and PHP's "mail" function as drivers for the
    | sending of e-mail. You may specify which one you're using throughout
    | your application here. By default, Laravel is setup for SMTP mail.
    |
    | Supported: "smtp", "mail", "sendmail", "mailgun", "mandrill", "ses", "log"
    |
    */
    'driver' => env('MAIL_DRIVER', 'smtp'),
    'host' => isset($_SERVER['MAIL_JR_ALERT_HOST']) && $_SERVER['MAIL_JR_ALERT_HOST'] ?$_SERVER['MAIL_JR_ALERT_HOST']:'mail.xin.com',
    'port' => isset($_SERVER['MAIL_JR_ALERT_PORT_SSL'])&& $_SERVER['MAIL_JR_ALERT_PORT_SSL']?$_SERVER['MAIL_JR_ALERT_PORT_SSL']:587,
    'from' => ['address' => null, 'name' => null],
    'encryption' => null,
    'username' => isset($_SERVER['MAIL_JR_ALERT_USER']) && $_SERVER['MAIL_JR_ALERT_USER']?$_SERVER['MAIL_JR_ALERT_USER']:'jinrong_alert@xin.com',
    'password' => isset($_SERVER['MAIL_JR_ALERT_PASS'])&& $_SERVER['MAIL_JR_ALERT_PASS'] ?$_SERVER['MAIL_JR_ALERT_PASS']:'L]b{tcrAql*>',
    /*
    |--------------------------------------------------------------------------
    | Sendmail System Path
    |--------------------------------------------------------------------------
    |
    | When using the "sendmail" driver to send e-mails, we will need to know
    | the path to where Sendmail lives on this server. A default path has
    | been provided here, which will work well on most of your systems.
    |
    */

    'sendmail' => '/usr/sbin/sendmail -bs',

    /*
    |--------------------------------------------------------------------------
    | Mail "Pretend"
    |--------------------------------------------------------------------------
    |
    | When this option is enabled, e-mail will not actually be sent over the
    | web and will instead be written to your application's logs files so
    | you may inspect the message. This is great for local development.
    |
    */

    'pretend' => false,
    //收件人
    'developer' => [
        'lihongtian@xin.com',
        'shenxin@xin.com'
    ],
    'wx'=>array(
        'lihongtian','shenxin'
    ),
    'sms'=>array(
        '18801358429',
        '18696840425'
    ),
];
