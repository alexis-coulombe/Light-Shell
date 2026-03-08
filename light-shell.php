<?php
/*
Plugin Name: Light Shell
Plugin URI: https://github.com/alexis-coulombe/Light-Shell/
Description: A lightweight xterm-like plugin to run shell commands.
Version: 1.0
*/

if (!defined('ABSPATH')) {
    wp_die();
}

const LS_VERSION = '1.0';

register_activation_hook(__FILE__, function () {
    if (PATH_SEPARATOR === ';') {
        exit('Light Shell is not compatible with Microsoft Windows.');
    }

    global $wp_version;
    if (version_compare($wp_version, '3.3', '<')) {
        exit(sprintf('Light Shell requires WordPress 3.3 or greater but your current version is %s.', htmlspecialchars($wp_version)));
    }

    if (PHP_VERSION_ID < 50600) {
        exit(sprintf('Light Shell requires PHP 5.6 or greater but your current version is %s.', PHP_VERSION));
    }
});

add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    if (!current_user_can('install_plugins') || !is_main_site()) {
        return $links;
    }

    $links[] = '<a href="' . get_admin_url(null, 'tools.php?page=lightshell') . '">Terminal</a>';
    return $links;
});

add_action('admin_footer', function () {
    if (empty($_GET['page']) || !is_main_site()) {
        return;
    }

    if ($_GET['page'] === 'lightshell') {
        wp_enqueue_script('lightshell_script', plugin_dir_url(__FILE__) . 'light-shell-terminal.js', array(), LS_VERSION);
    }
});

add_action('admin_menu', function () {
    if (!is_main_site()) {
        return;
    }

    global $menu_hook;
    $menu_hook = add_submenu_page('tools.php', 'Light Shell', 'Light Shell', 'install_plugins', 'lightshell', 'lightshell_main_menu');
});

function lightshell_main_menu()
{
    $tabs = ['terminal', 'settings'];

    if (!isset($_GET['lightshelltab']) || !in_array($_GET['lightshelltab'], $tabs, true)) {
        $_GET['lightshelltab'] = 'terminal';
    }

    $lightshell_menu = "lightshell_menu_{$_GET['lightshelltab']}";
    $lightshell_menu();
}

function lightshell_menu_terminal()
{
    $lightshell_options = lightshell_menu_get_settings();
    $userinfo = posix_getpwuid(posix_getuid());

    // Get current working directory:
    if ($lightshell_options['user-home'] === 'abspath') {
        $cwd = htmlspecialchars(rtrim(ABSPATH, '/'));
    } else {
        $cwd = htmlspecialchars(rtrim($userinfo['dir'], '/'));
    }

    $last_login = '';
    $kernel_info = '';

    // Get/set last login:
    if (!empty($lightshell_options['last_login'])) {
        list ($time, $user, $ip) = explode(':', $lightshell_options['last_login'], 3);
        $date = date('d/m/y H:i:s', $time);
        $last_login = sprintf('Last login: %s, %s from %s', htmlspecialchars($user), $date, htmlspecialchars($ip) . '\n');
    }

    // Get the current user (system and WordPress) + his/her IP:
    $user = htmlspecialchars($userinfo['name']);
    $ip = htmlspecialchars($_SERVER['REMOTE_ADDR']);
    $time = time();

    if ($user === 'root') {
        ?>
        <div class="error notice">
            <p>
                <?php echo 'The current user is root' ?>
            </p>
        </div>
        <?php
    }
    ?>

    <div class="notice">
        <p>
            <?php echo 'Do not try to run interactive commands, you can\'t. If you run one by mistake and are stuck at the prompt, refresh the page.'; ?>
        </p>
    </div>

    <?php
    $current_user = wp_get_current_user();
    $wpuser = htmlspecialchars($current_user->user_login);

    // Save options to the database:
    $lightshell_options['last_login'] = "$time:$wpuser:$ip";
    $lightshell_options['version'] = LS_VERSION;
    update_option('lightshell_options', $lightshell_options);

    // Try to get the kernel info:
    list($uname, $null) = @run_command('uname -a', $lightshell_options['php-function']);
    if (!empty($uname)) {
        $kernel_info = htmlspecialchars(trim($uname)) . '\n';
    } else {
        // Maybe we are running on a shared hosting
        ?>
        <div class="error notice is-dismissible">
            <p>
                <?php printf('Unable to run a shell command. Make sure that you are allowed to run %sPHP program execution functions%s.', '<a href="http://php.net/manual/en/ref.exec.php">', '</a>') ?>
            </p>
        </div>
        <?php
    }
    ?>
    <style>
        .terminal-user {
            background-color: #000;
            color: #fff;
            font-family: Consolas, Monaco, monospace, serif;
            font-size: 13px;
        }

        .terminal {
            width: 100%;
            height: 100vh;
            line-height: 1.4;
            padding: 4px 6px 1px;
            resize: vertical;
        }

        #terminal.error, #visual-bell.error {
            animation: flash 0.1s ease-out 2;
        }

        @keyframes flash {
            0%, 100% {
                border-color: #000;
            }
            50% {
                border-color: #f00;
                box-shadow: 0 0 10px #f00;
            }
        }
    </style>
    <script>
        var lightshell_ajax_nonce = "<?php echo wp_create_nonce('lightshell_menu_terminal'); ?>";
        var prompt = "<?php echo "$user:$cwd" ?> $ ";
        var user = "<?php echo $user ?>";
        var cwd = "<?php echo $cwd ?>";
        var abspath = "<?php echo htmlspecialchars(rtrim(ABSPATH, '/')) ?>";
        var exec = "<?php echo htmlspecialchars($lightshell_options['php-function']) ?>";
        var last_login = "<?php echo $kernel_info . $last_login . '\n' ?>";
        var op_cancelled = "<?php echo esc_js('operation cancelled') ?>";
        var logout_url = "<?php echo html_entity_decode(wp_logout_url()); ?>";
        var logout_msg = "<?php echo esc_js('Log out of WordPress?') ?>";
        var unknown_err = "<?php echo esc_js('Light Shell: error, no data received') ?>";
        var version = "<?php echo '\nv' . LS_VERSION ?>";
        var scrollback = <?php echo (int)$lightshell_options['scrollback'] ?>;
    </script>
    <?php

    // If the blog is setup to use a right-to-left language and the user runs IE/Edge browser
    // we inform them that it is not compatible:
    if (is_rtl() && preg_match('/MSIE|Trident|Edge/', $_SERVER['HTTP_USER_AGENT'])) {
        echo '<div class="notice-warning notice is-dismissible"><p>Because your current locale is RTL (Right To Left script), the terminal will not work well with your IE/Edge browser. Consider using another browser that is compatible (Firefox, Chrome, Opera or Safari)</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>Light Shell</h1>

        <h2 class="nav-tab-wrapper wp-clearfix">
            <a href="?page=lightshell&lightshelltab=terminal" class="nav-tab nav-tab-active">Terminal</a>
            <a href="?page=lightshell&lightshelltab=settings" class="nav-tab">Settings</a>
        </h2>

        <table style="width:100%;padding-top:4px">
            <tr>
                <td width="100%">
                    <textarea dir="auto" ondragstart="return false;" id="terminal" class="terminal terminal-user" onMouseOver="this.focus();" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" wrap="soft"></textarea>
                </td>
            </tr>
        </table>
    </div>
    <?php
}

function lightshell_menu_settings()
{
    if (isset($_POST['save-settings'])) {
        if (empty($_POST['lightshellnonce']) || !wp_verify_nonce($_POST['lightshellnonce'], 'save_settings')) {
            wp_nonce_ays('save_settings');
        }
        lightshell_menu_save_settings();
        echo '<div class="updated notice is-dismissible"><p>Your changes have been saved.</p></div>';
    }

    $lightshell_options = lightshell_menu_get_settings();
    ?>
    <div class="wrap">
        <h1>Light shell</h1>

        <h2 class="nav-tab-wrapper wp-clearfix">
            <a href="?page=lightshell&lightshelltab=terminal" class="nav-tab">Terminal</a>
            <a href="?page=lightshell&lightshelltab=settings" class="nav-tab nav-tab-active">Settings</a>
        </h2>

        <form method="post">
            <h3>Terminal</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">Use the following PHP function for command execution</th>
                    <td>
                        <p>
                            <label>
                                <input type="radio" name="php-function" value="exec"<?php
                                checked($lightshell_options['php-function'], 'exec') ?> /><code>exec</code>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="radio" name="php-function" value="shell_exec"<?php
                                checked($lightshell_options['php-function'], 'shell_exec') ?> /><code>shell_exec</code>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="radio" name="php-function" value="system"<?php
                                checked($lightshell_options['php-function'], 'system') ?> /><code>system</code>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="radio" name="php-function" value="passthru"<?php
                                checked($lightshell_options['php-function'], 'passthru') ?> /><code>passthru</code>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="radio" name="php-function" value="popen"<?php
                                checked($lightshell_options['php-function'], 'popen') ?> /><code>popen</code>
                            </label>
                        </p>
                    </td>
                </tr>

                <?php
                $userinfo = posix_getpwuid(posix_getuid()); ?>
                <tr>
                    <th scope="row">Default working directory</th>
                    <td>
                        <p>
                            <label>
                                <input type="radio" name="user-home" value="abspath"<?php
                                checked($lightshell_options['user-home'], 'abspath') ?> />
                                <span>Wordpress ABSPATH <code><?php
                                        echo htmlspecialchars(ABSPATH); ?></code></span>
                            </label>
                        </p>
                        <p>
                            <label>
                                <input type="radio" name="user-home" value="homedir"<?php
                                checked($lightshell_options['user-home'], 'homedir') ?> />
                                <span>User home directory <code><?php
                                        echo htmlspecialchars($userinfo['dir']); ?></code></span>
                            </label>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Scrollback</th>
                    <td>
                        <label><?php
                            printf('Limit scrollback to %s lines', '<input type="number" class="small-text" name="scrollback" step="1" min="1" max="3000" value="' . (int)$lightshell_options['scrollback'] . '" />') ?></label>
                        <br>
                        <span class="description">Max 3,000 lines.</span>
                    </td>
                </tr>
            </table>

            <input class="button-primary" type="submit" name="save-settings" value="Save Settings"/>
            <?php
            wp_nonce_field('save_settings', 'lightshellnonce', 0); ?>
        </form>
    </div>
    <?php
}

function lightshell_menu_get_settings()
{
    if (empty($lightshell_options['php-function']) || !preg_match('/^(?:exec|shell_exec|system|passthru|popen)$/', $lightshell_options['php-function'])) {
        $lightshell_options['php-function'] = 'exec';
    }

    if (!isset($lightshell_options['user-home']) || $lightshell_options['user-home'] === 'abspath') {
        $lightshell_options['user-home'] = 'abspath';
    } else {
        $lightshell_options['user-home'] = 'homedir';
    }

    if (!empty($lightshell_options['scrollback'])) {
        $lightshell_options['scrollback'] = (int)$lightshell_options['scrollback'];
        if ($lightshell_options['scrollback'] < 1 || $lightshell_options['scrollback'] > 3000) {
            $lightshell_options['scrollback'] = 512;
        }
    } else {
        $lightshell_options['scrollback'] = 512;
    }

    return $lightshell_options;
}

function lightshell_menu_save_settings()
{
    $lightshell_options = get_option('lightshell_options');

    if (empty($_POST['php-function']) || !preg_match('/^(?:exec|shell_exec|system|passthru|popen)$/', $_POST['php-function'])) {
        $lightshell_options['php-function'] = 'exec';
    } else {
        $lightshell_options['php-function'] = htmlspecialchars($_POST['php-function']);
    }

    if (empty($_POST['user-home']) || !preg_match('/^(?:abspath|homedir)$/', $_POST['user-home'])) {
        $lightshell_options['user-home'] = 'abspath';
    } else {
        $lightshell_options['user-home'] = htmlspecialchars($_POST['user-home']);
    }

    if (!empty($_POST['scrollback'])) {
        $lightshell_options['scrollback'] = (int)$_POST['scrollback'];
        $lightshell_options['scrollback'] = max(1, min(3000, $lightshell_options['scrollback']));
    } else {
        $lightshell_options['scrollback'] = 512;
    }

    $lightshell_options['version'] = LS_VERSION;

    update_option('lightshell_options', $lightshell_options);
}

add_action('wp_ajax_lightshellajax', 'lightshellajax_callback');

function lightshellajax_callback()
{
    if (!current_user_can('install_plugins') || !is_main_site()) {
        wp_die(401);
    }

    if (!check_ajax_referer('lightshell_menu_terminal', 'lightshell_ajax_nonce', false)) {
        wp_die('/::' . 'Security nonces do not match. Try to reload this page to renew them.');
    }

    // Path to return in case of fatal error:
    $if_error = htmlspecialchars(rtrim(ABSPATH, '/')) . '::';

    if (empty($_POST['cmd']) || empty($_POST['cwd']) || empty($_POST['exec']) || empty($_POST['abs'])) {
        wp_die($if_error . 'missing command, path, function or abspath');
    }

    $cmd = stripslashes(base64_decode(trim($_POST['cmd'])));
    $cwd = stripslashes(trim($_POST['cwd']));
    $abs = stripslashes(trim($_POST['abs']));
    // Set the ABSPATH variable, go to the current working directory,
    // run the command, redirect STDERR to STDOUT and return the current
    // working directory (it may have been changed e.g., `cd /foo/bar`):
    $command = sprintf("ABSPATH=%s;cd %s;%s 2>&1;echo [-{-`pwd`-}-]", $abs, $cwd, $cmd);

    list($res, $ret_var) = @run_command($command, trim($_POST['exec']));

    // Split the PWD and the data returned by the command:
    if (preg_match('`^(.+)?\[-{-(/.*?)-}-]`s', $res, $match)) {
        // Turn the string into an array...
        $res_array = explode("\n", $match[1]);
        // ...keep only the last $_POST['scrollback'] lines and re-create the string...
        $res_str = implode("\n", array_slice($res_array, -$_POST['scrollback']));
        // ...and return it to Light Shell terminal:
        echo rtrim($match[2] . '::' . stripcslashes($res_str));
    } elseif (!empty($ret_var)) {
        echo $if_error . sprintf('Light Shell: error %s', (int)$ret_var);
    } else {
        echo $if_error . 'Unknown error. Are you allowed to run PHP program execution functions?';
    }

    wp_die();
}

function run_command($command, $function)
{
    $ret_var = '';
    $res = '';

    switch ($function) {
        case 'shell_exec':
        case 'backtick':
        {
            $res = shell_exec($command);
            break;
        }
        case 'system':
        {
            ob_start();
            system($command, $ret_var);
            $res = ob_get_clean();
            break;
        }
        case 'passthru':
        {
            ob_start();
            passthru($command, $ret_var);
            $res = ob_get_clean();
            break;
        }
        case 'popen':
        {
            if (($handle = popen($command, 'r')) !== false) {
                while (!feof($handle)) {
                    $res .= fgets($handle);
                }
                pclose($handle);
            }
            break;
        }
        default:
        {
            if (exec($command, $res, $ret_var)) {
                $res = implode('\n', $res);
            }
        }
    }

    return array($res, $ret_var);
}