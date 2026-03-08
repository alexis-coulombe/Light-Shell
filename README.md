# Light Shell

**Tags:** xterm, terminal, command, bash, shell  
**Requires at least:** 4.9.4  
**Tested up to:** 6.9.1  
**Stable tag:** 1.0  
**License:** GPLv3 or later  
**Requires PHP:** 5.6  
**License URI:** http://www.gnu.org/licenses/gpl-3.0.html  

Heavily modified version of the WPTerm plugin providing a lightweight xterm-style terminal inside the admin dashboard.  
Designed to remain minimal in both features and size (~23 KB).

---

## Description

Light Shell provides a minimal terminal interface inside the WordPress admin panel to execute non-interactive shell commands.

It can be useful for quick server tasks such as:

- Checking logs
- Inspecting running processes
- Viewing network connections
- Adjusting file permissions

Because it executes real shell commands on the server, misuse can cause serious damage. Only use this plugin if you understand the commands you are running.

---

## Compatibility

This plugin works only on **Unix-like systems** and is not compatible with **Windows servers**.

It relies on PHP execution functions such as `exec()` or `shell_exec()`. Some hosting providers disable these functions, which will prevent the plugin from working.

---

## Requirements

- WordPress **4.9.4** or higher  
- PHP **5.6** or higher
