<?php
if (!file_exists('madeline.phar')) {
    copy('https://phar.madelineproto.xyz/madeline.php', 'madeline.phar');
}
require 'madeline.phar';
