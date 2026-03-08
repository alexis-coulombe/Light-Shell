const terminal = document.getElementById('terminal');

if(!terminal) {
	throw new Error( 'Terminal element not found.' );
}

let prompt_buffer = last_login + prompt;
let prompt_length = prompt_buffer.length;
let history_buffer = [];
let history_pos = 0;
let running = 0;
let command = '';
let data_returned = '';

terminal.value = prompt_buffer;
setCursorPosition(terminal, prompt_length );

function setCursorPosition(el, pos) {
	if (el.setSelectionRange) {
		el.focus();
		el.setSelectionRange(pos, pos);
	}
}

function getCursorPosition(el) {
	if (el.selectionStart) {
		return el.selectionStart;
	}
	return 0;
}

/* ================================================================== */

// Hook keydown and paste events:
['keydown', 'paste'].forEach(evt => terminal.addEventListener(evt, (event) => {
	if(!terminal) {
		console.error( 'ls: Terminal element not found.' );
		return false;
	}

	if (terminal.classList.contains('error')) {
		terminal.classList.remove('error');
	}

	data_returned = '';
	command = '';

	if (running) {
		alert('Operations in progress, refresh to cancel');
		return false;
	}

	// Scroll down to the prompt if a key is pressed
	if (event.key) {
		scrollToBottom();
	}

	const cursor = getCursorPosition(terminal);

	// Re-position the cursor, if it was moved elsewhere before the prompt:
	if ( cursor < prompt_length ) {
		setCursorPosition(terminal, terminal.value.length );
	}

	switch(event.key) {
		case 'Home':
		case 'ArrowLeft':
		case 'Backspace': {
			if (event.key === 'Home') {
				event.preventDefault();
				setCursorPosition(terminal, prompt_length);
				return false;
			}

			if (cursor === prompt_length) {
				event.preventDefault();
				notifyTerminal();
				return false;
			}
			break;
		}
		case 'ArrowUp':	{
			if (history_buffer[history_pos] != null) {
				terminal.value = prompt_buffer + history_buffer[history_pos];
				++history_pos;
			} else {
				notifyTerminal();
			}

			// Force scroll down (Opera, Chrome...):
			terminal.scrollTop = terminal.scrollHeight;
			return false;
		}
		case 'ArrowDown': {
			if (history_buffer[history_pos - 1] != null) {
				terminal.value = prompt_buffer + history_buffer[history_pos - 1];
				--history_pos;
			} else {
				terminal.value = prompt_buffer;
				notifyTerminal();
			}

			// Force scroll down
			terminal.scrollTop = terminal.scrollHeight;
			return false;
		}
		case 'Enter': {
			command = terminal.value.substring(prompt_length).trim();

			if (command === '') {
				prompt = '';
				updateRequest();
			}

			if (command === 'clear' || command === 'reset' || command === 'cls') {
				clearTerminal();
			} else if (command === 'exit' || command === 'logout' || command === 'quit') {
				if (confirm(logout_msg)) {
					window.location.replace(logout_url);
				}
				data_returned = '\nls: ' + op_cancelled;
				updateRequest();
			} else if (command === 'history') {
				let x = 1;
				for (let i = history_buffer.length; i >= 0; --i) {
					if (history_buffer[i] != null) {
						data_returned += '\n ' + x++ + ' ' + history_buffer[i];
					}
				}
				// Add the `history` command too:
				data_returned += '\n ' + x++ + ' history';
				updateRequest();
			} else {
				runCommand();
			}

			return false;
		}
	}

	return true;
}));

function updateRequest() {
	updatePrompt();

	if (command !== '') {
		history_buffer.unshift( command );
	}

	history_pos = 0;
}

function updatePrompt() {
	// Build the terminal output:
	prompt_buffer += command + data_returned + "\n" + prompt;
	// Turn the whole string into an array...
	let res_array = prompt_buffer.split( "\n" );
	// ...keep only the last `scrollback` lines...
	res_array = res_array.slice( - scrollback );
	// ...re-create the string...
	prompt_buffer = res_array.join( "\n" );
	prompt_length = prompt_buffer.length;
	// ...and refresh the terminal:
	terminal.value = prompt_buffer;
	scrollToBottom();
}

function scrollToBottom() {
	terminal.scrollTop = terminal.scrollHeight;
}

function clearTerminal() {
	const terminal = document.getElementById( 'terminal' );

	if (terminal) {
		terminal.value = prompt;
		prompt_buffer = prompt;
		prompt_length = prompt_buffer.length;
	}
}

function notifyTerminal() {
	const terminal = document.getElementById( 'terminal' );

	if (terminal) {
		terminal.classList.add( 'error' );
	}
}

function runCommand() {
	running = true;

	fetch(ajaxurl, {
		method: 'POST',
		body: new URLSearchParams({
			'action': 'lightshellajax',
			'lightshell_ajax_nonce': lightshell_ajax_nonce,
			'cmd': btoa( command ),
			'cwd': cwd,
			'exec': exec,
			'abs': abspath,
			'scrollback': scrollback
		}),
	})
		.then(response => response.text())
		.then(response => {
			const res = response.split('::');
			// We may have more than one '::' occurrence:
			res.push(res.splice(1).join('::'));

			if (res[0] !== '') {
				cwd = res[0].trim();
				prompt = user + ':' + cwd + ' $ ';
				// Data to output. We don't need to sanitise it, because the DOM is ready:
				data_returned = res[1] !== '' ? '\n' + res[1] : '';
			} else {
				data_returned = '\n' + unknown_err;
			}

			updateRequest();
			running = false;
		})
		.catch(err => {
			data_returned = '\n' + err;
			updateRequest();
			running = false;
		});
}