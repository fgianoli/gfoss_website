<?php
namespace GFOSS_Members;

if ( ! defined( 'ABSPATH' ) ) { exit; }

class Deactivator {
    public static function deactivate(): void {
        Cron::unschedule();
        flush_rewrite_rules();
    }
}
