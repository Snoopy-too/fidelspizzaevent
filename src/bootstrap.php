<?php
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';
require_once __DIR__ . '/../config.php';

use FidelsPizza\Infrastructure\Container\ServiceContainer;

function getServiceContainer(): ServiceContainer
{
    static $container = null;
    if ($container === null) {
        $pdo = getDB();
        $siteConfig = getSiteConfig();
        $siteConfig['site_url'] = defined('SITE_URL') ? SITE_URL : 'http://localhost/fidelspizzaevent';
        $fromEmail = defined('FROM_EMAIL') ? FROM_EMAIL : 'noreply@yoursite.com';
        $fromName = defined('FROM_NAME') ? FROM_NAME : "Fidel's Pizza Event";

        $container = new ServiceContainer($pdo, $siteConfig, $fromEmail, $fromName);
    }
    return $container;
}
