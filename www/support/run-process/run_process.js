// Long running process Javascript SDK.
// (C) 2019 CubicleSoft.  All Rights Reserved.

(function() {
	var EscapeHTML = function(text) {
		var map = {
			'&': '&amp;',
			'<': '&lt;',
			'>': '&gt;',
			'"': '&quot;',
			"'": '&#039;'
		};

		return text.replace(/[&<>"']/g, function(m) { return map[m]; });
	}

	var CreateNode = function(node, classes, attrs, styles) {
		var elem = document.createElement(node);

		if (classes)
		{
			for (var x = 0; x < classes.length; x++)  elem.classList.add(classes[x]);
		}

		if (attrs)  Object.assign(elem, attrs);

		if (styles)  Object.assign(elem.style, styles);

		return elem;
	};

	function DebounceWithThrottle(func, debouncewait, throttlewait, callatstart, callatend) {
		var debouncetimeout, throttletimeout;

		return function() {
			var context = this, args = arguments;

			if (callatstart && !debouncetimeout)  func.apply(context, [].concat(args, 'start'));

			if (!throttletimeout && throttlewait > 0)
			{
				throttletimeout = setInterval(function() {
					func.apply(context, [].concat(args, 'during'));
				}, throttlewait);
			}

			clearTimeout(debouncetimeout);
			debouncetimeout = setTimeout(function() {
				clearTimeout(throttletimeout);
				throttletimeout = null;
				debouncetimeout = null;

				if (callatend)  func.apply(context, [].concat(args, 'end'));
			}, debouncewait);
		};
	};

	var addon_fit = false;

	window.ExecTerminal = function(parentelem, options) {
		if (this === window)
		{
			console.error('[ExecTerminal] Error:  Did you forget to use the "new" keyword?');

			return;
		}

		if (!window.Terminal)
		{
			console.error('[ExecTerminal] Error:  Did you forget to load "xterm.js"?  Also be sure to load "addons/fit.js" so that terminals can be stretched to fit the space.');

			return;
		}

		if (!addon_fit)
		{
			window.Terminal.applyAddon(window.fit);

			addon_fit = true;
		}

		var allowterminalfit = false, echoinput = false, secureinput = false;
		var $this = this;

		var defaults = {
			context: null,

			stdinopen: true,
			connected: true,
			running: true,
			hadstderr: false,

			attached: true,
			fullscreen: false,

			attachedbutton: null,
			fullscreenbutton: true,
			terminatebutton: null,
			removebutton: null,

			inittitle: '',
			initviewportheight: 0.5,

			inputmode: 'none',
			onenter: null,
			onkey: null,
			ondata: null,
			onresize: null,

			langmap: {}
		};

		$this.settings = Object.assign({}, defaults, options);

		// Multilingual translation.
		var Translate = function(str) {
			return ($this.settings.langmap[str] ? $this.settings.langmap[str] : str);
		};

		$this.terminal = new window.Terminal();

		var elems = {
			terminalwrap: CreateNode('div', ['ext-run-process-terminal-wrap']),
			titlebar: CreateNode('div', ['ext-run-process-titlebar']),
			readlinewrap: CreateNode('div', ['ext-run-process-readline-wrap', 'ext-run-process-hidden']),
			vertsizer: CreateNode('div', ['ext-run-process-terminal-vert-sizer']),

			status_disconnected: CreateNode('div', ['ext-run-process-status', 'ext-run-process-icon--warning', 'ext-run-process-status-disconnected'], { tabindex: '-1', title: Translate('Disconnected') }),
			status_running: CreateNode('div', ['ext-run-process-status', 'ext-run-process-icon--status-running', 'ext-run-process-status-running'], { tabindex: '-1', title: Translate('Running') }),
			status_terminated: CreateNode('div', ['ext-run-process-status', 'ext-run-process-icon--status-terminated', 'ext-run-process-status-terminated'], { tabindex: '-1', title: Translate('Terminated') }),
			status_hadstderr: CreateNode('div', ['ext-run-process-status', 'ext-run-process-icon--stderr', 'ext-run-process-status-hadstderr'], { tabindex: '-1', title: Translate('Output on stderr') }),

			title: CreateNode('div', ['ext-run-process-title'], { tabindex: '-1' }),

			button_attached: CreateNode('button', ['ext-run-process-button', 'ext-run-process-icon--attached', 'ext-run-process-attached'], { title: Translate('Attached') }),
			button_detached: CreateNode('button', ['ext-run-process-button', 'ext-run-process-icon--detached', 'ext-run-process-detached', 'ext-run-process-hidden'], { title: Translate('Detached') }),
			button_fullscreen: CreateNode('button', ['ext-run-process-button', 'ext-run-process-icon--fullscreen'], { title: Translate('Fullscreen') }),
			button_fullscreen_exit: CreateNode('button', ['ext-run-process-button', 'ext-run-process-icon--fullscreen-exit', 'ext-run-process-hidden'], { title: Translate('Exit fullscreen') }),
			button_terminate: CreateNode('button', ['ext-run-process-button', 'ext-run-process-icon--terminate', 'ext-run-process-terminate'], { title: Translate('Terminate process') }),
			button_remove: CreateNode('button', ['ext-run-process-button', 'ext-run-process-icon--remove', 'ext-run-process-remove'], { title: Translate('Remove') }),

			terminalelem: CreateNode('div', ['ext-run-process-terminal']),

			readline: CreateNode('input', ['ext-run-process-readline'], { type: 'text' }),

			button_secure_input_off: CreateNode('button', ['ext-run-process-button', 'ext-run-process-icon--secure-input'], { title: Translate('Secure input is off') }),
			button_secure_input_on: CreateNode('button', ['ext-run-process-button', 'ext-run-process-icon--secure-input', 'ext-run-process-secure-input', 'ext-run-process-hidden'], { title: Translate('Secure input is on') })
		};

		elems.title.innerHTML = EscapeHTML($this.settings.inittitle);

		// Attach elements to DOM.
		elems.titlebar.appendChild(elems.status_disconnected);
		elems.titlebar.appendChild(elems.status_running);
		elems.titlebar.appendChild(elems.status_terminated);
		elems.titlebar.appendChild(elems.status_hadstderr);
		elems.titlebar.appendChild(elems.title);
		elems.titlebar.appendChild(elems.button_attached);
		elems.titlebar.appendChild(elems.button_detached);
		elems.titlebar.appendChild(elems.button_fullscreen);
		elems.titlebar.appendChild(elems.button_fullscreen_exit);
		elems.titlebar.appendChild(elems.button_terminate);
		elems.titlebar.appendChild(elems.button_remove);

		elems.readlinewrap.appendChild(elems.readline);
		elems.readlinewrap.appendChild(elems.button_secure_input_off);
		elems.readlinewrap.appendChild(elems.button_secure_input_on);

		elems.terminalwrap.appendChild(elems.titlebar);
		elems.terminalwrap.appendChild(elems.terminalelem);
		elems.terminalwrap.appendChild(elems.readlinewrap);
		elems.terminalwrap.appendChild(elems.vertsizer);

		parentelem.appendChild(elems.terminalwrap);

		// Attach the Xterm.js terminal.
		$this.terminal.open(elems.terminalelem);

		// Perform initial height calculation.  The default height is 50% of the viewport height or 5 rows, whichever is greater.
		var newrows = Math.round((document.documentElement.clientHeight * $this.settings.initviewportheight) / $this.terminal._core.renderer.dimensions.actualCellHeight);
		var newheight = Math.max(newrows, 5.0) * $this.terminal._core.renderer.dimensions.actualCellHeight;
		elems.terminalelem.style.height = newheight + 'px';

		// Internal functions.

		var UpdateTerminalFit = function() {
			if (!allowterminalfit || !$this)  return;

			$this.terminal.fit();

			elems.terminalelem.setAttribute('data-size', $this.terminal.cols + ' x ' + $this.terminal.rows);

			// This is necessary when there is a lot of data being output.
			var scrollint, scrollequal = 0;
			var ScrollToBottom = function() {
//console.log('scrolling: ' + $this.terminal._core.buffer.ydisp + ', ' + $this.terminal._core.buffer.ybase + ', ' + ($this.terminal._core._userScrolling ? 'yes' : 'no') + ', ' + ($this.terminal._core._writeInProgress ? 'yes' : 'no'));
				if ($this && ($this.terminal._core.buffer.ydisp != $this.terminal._core.buffer.ybase || $this.terminal._core._userScrolling || $this.terminal._core._writeInProgress))
				{
					$this.terminal.scrollLines(10000000);

					scrollequal = 0;
				}
				else if (scrollequal < 3)
				{
					scrollequal++;
				}
				else
				{
					clearInterval(scrollint);
				}
			};

			scrollint = setInterval(ScrollToBottom, 50);
		};

		// Window resize handler.
		var WindowResizeHandler = DebounceWithThrottle(function(e, dtmode) {
			if (dtmode === 'start')  document.body.classList.add('ext-run-process-resizing');
			if (dtmode === 'end')  document.body.classList.remove('ext-run-process-resizing');

			window.requestAnimationFrame(UpdateTerminalFit);
		}, 500, 500, true, true);

		window.addEventListener('resize', WindowResizeHandler);

		// Handle user resizing with the vertical resizer bar.
		var draginfo = null;
		var VertResizeUpdate = function(e) {
			e.preventDefault();

			// Determine the new terminal size.
			var newrows = Math.max(Math.round(draginfo.rows + (((e.type === 'touchmove' ? e.touches[0].clientY : e.clientY) - draginfo.y) / $this.terminal._core.renderer.dimensions.actualCellHeight)), 5);
			var newcols = draginfo.cols;

			if (newrows != $this.terminal.rows)
			{
				window.requestAnimationFrame(function() {
					var newheight = newrows * $this.terminal._core.renderer.dimensions.actualCellHeight;

					elems.terminalelem.style.height = newheight + 'px';

					UpdateTerminalFit();
				});
			}
		};

		var StopVertResize = function(e) {
			if (!draginfo)  return;

			document.removeEventListener('touchmove', VertResizeUpdate);
			document.removeEventListener('touchend', StopVertResize);
			document.removeEventListener('mousemove', VertResizeUpdate);
			document.removeEventListener('mouseup', StopVertResize);

			draginfo = null;

			document.body.classList.remove('ext-run-process-resizing');
			document.body.classList.remove('ext-run-process-vert-resizing');
			elems.terminalwrap.classList.remove('ext-run-process-terminal-resizing');
		};

		var StartVertResize = function(e) {
			e.preventDefault();

			if (draginfo)  return;

			// In case there are styles to apply while dragging (e.g. an overlay containing the size).
			elems.terminalwrap.classList.add('ext-run-process-terminal-resizing');

			// Prevent user selection of text while dragging.
			document.body.classList.add('ext-run-process-vert-resizing');
			document.body.classList.add('ext-run-process-resizing');

			// Calculate the offset of the event to the resize region and start tracking move events.
			draginfo = {
				rect: elems.terminalelem.getBoundingClientRect()
			};
			if (e.type === 'touchstart')
			{
				draginfo.x = e.touches[0].clientX;
				draginfo.y = e.touches[0].clientY;

				document.addEventListener('touchmove', VertResizeUpdate);
				document.addEventListener('touchend', StopVertResize);
			}
			else
			{
				draginfo.x = e.clientX;
				draginfo.y = e.clientY;

				document.addEventListener('mousemove', VertResizeUpdate);
				document.addEventListener('mouseup', StopVertResize);
			}

			draginfo.rows = $this.terminal.rows;
			draginfo.cols = $this.terminal.cols;
		};

		elems.vertsizer.addEventListener('mousedown', StartVertResize);
		elems.vertsizer.addEventListener('touchstart', StartVertResize);

		// Focus on the terminal when clicking on the title bar.
		var TitlebarFocusHandler = function() {
			$this.terminal.focus();
		};

		elems.titlebar.addEventListener('click', TitlebarFocusHandler);

		// Focus on the text input when clicking the readline wrapper.
		var ReadlineWrapClickHandler = function(e) {
			elems.readline.focus();
		};

		var ReadlineClickHandler = function(e) {
			e.stopPropagation();
		};

		elems.readlinewrap.addEventListener('click', ReadlineWrapClickHandler);
		elems.readline.addEventListener('click', ReadlineClickHandler);

		// Hide the terminal cursor when the readline input is focused.
		var ReadlineFocusHandler = function(e) {
			$this.terminal.write('\x1B[?25l');
		}

		elems.readline.addEventListener('focus', ReadlineFocusHandler);

		// Set scrollback limit to 10,000 lines.
		$this.terminal.setOption('scrollback', 10000);

		// Hide the cursor.
		$this.terminal.write('\x1B[?25l');

		// Update the title if the script emits a new title.
		var TerminalTitleHandler = function(title) {
			elems.title.innerHTML = EscapeHTML(title);
		};

		$this.terminal.on('title', TerminalTitleHandler);

		// Echo input keys to the terminal when echo is turned on.
		var TerminalKeyHandler = function(key, e) {
			if ($this.settings.stdinopen)
			{
				if (echoinput)
				{
					if (e.key.length > 1 || e.altKey || e.ctrlKey || e.metaKey)  return;

					$this.terminal.write(key);
				}

				// Pass keys to a registered handler in the context of the ExecTerminal.
				if ($this.settings.onkey)  $this.settings.onkey.call($this, key, e);
			}
		};

		// Pass data to a registered handler in the context of the ExecTerminal.
		var TerminalDataHandler = function(data) {
			if ($this.settings.stdinopen && $this.settings.ondata)  $this.settings.ondata.call($this, data);
		};

		// Pass size info to a registered handler in the context of the ExecTerminal.
		var TerminalResizeHandler = function(size) {
			if ($this.settings.onresize)  $this.settings.onresize.call($this, size);
		};

		$this.terminal.on('key', TerminalKeyHandler);
		$this.terminal.on('data', TerminalDataHandler);
		$this.terminal.on('resize', TerminalResizeHandler);

		// Handle readline input.
		var history = [['', false]], historypos = 0;
		var ReadlineKeydownHandler = function(e) {
			if ($this.settings.stdinopen && ((!secureinput && (e.key === 'ArrowUp' || e.key === 'Up' || e.key === 'ArrowDown' || e.key === 'Down')) || e.key === 'Enter'))
			{
				// Update the history array with the current value.
				var elem = e.target;
				var val = elem.value;
				var updatepos = (!secureinput && (!history[historypos][1] || history[historypos][0] === val) ? historypos : history.length - 1);

				if (!secureinput)  history[updatepos][0] = val;

				if (history[history.length - 1][0] !== '')  history.push(['', false]);

				if (e.key === 'ArrowUp' || e.key === 'Up')
				{
					if (historypos)  historypos--;
				}
				else if (e.key === 'ArrowDown' || e.key === 'Down')
				{
					if (historypos < history.length - 1)  historypos++;
				}
				else if (e.key === 'Enter')
				{
					if ($this.settings.onenter)  $this.settings.onenter.call($this, val + '\n', secureinput);
//console.log('Send:  ' + val);

					if (secureinput)  $this.terminal.write('\r\n');
					else
					{
						$this.terminal.write(val + '\r\n');

						history[updatepos][1] = true;
					}

					historypos = history.length - 1;

					UpdateTerminalFit();
				}

				elem.value = history[historypos][0];

				// Move the cursor to the end.
				setTimeout(function() {
					if (elem.setSelectionRange)  elem.setSelectionRange(elem.value.length, elem.value.length);
					else if (elem.createTextRange)
					{
						var r = elem.createTextRange();
						r.moveStart('character', elem.value.length);
						r.collapse();
						r.select();
					}
				}, 0);
			}
		};

		elems.readline.addEventListener('keydown', ReadlineKeydownHandler);

		var EnableSecureInput = function(e) {
			if (e)  e.preventDefault();

			elems.readline.setAttribute('type', 'password');

			elems.button_secure_input_off.classList.add('ext-run-process-hidden');
			elems.button_secure_input_on.classList.remove('ext-run-process-hidden');

			secureinput = true;
		};

		var DisableSecureInput = function(e) {
			if (e)  e.preventDefault();

			elems.readline.setAttribute('type', 'text');

			elems.button_secure_input_off.classList.remove('ext-run-process-hidden');
			elems.button_secure_input_on.classList.add('ext-run-process-hidden');

			secureinput = false;
		};

		elems.button_secure_input_off.addEventListener('click', EnableSecureInput);
		elems.button_secure_input_on.addEventListener('click', DisableSecureInput);

		// Redirect focus from the terminal if the mode is readline.
		var termfocused = false;

		var TerminalFocusHandler = function() {
			if (!elems.readlinewrap.classList.contains('ext-run-process-hidden'))  elems.readline.focus();
			else  termfocused = true;
		};

		var TerminalBlurHandler = function() {
			termfocused = false;
		};

		$this.terminal.on('focus', TerminalFocusHandler);
		$this.terminal.on('blur', TerminalBlurHandler);

		// Add a custom OSC handler for controlling input mode and echo state from the running application.
		var CustomOSCHandler = function(str) {
//console.log(str);

			var hasfocus = (termfocused || elems.readline === document.activeElement);

			if (str === 'interactive')
			{
				// The 'interactive' mode is the default for a standard shell (i.e. stdin is echoed back on stdout).
				elems.readlinewrap.classList.add('ext-run-process-hidden');
				DisableSecureInput();

				echoinput = false;

				// Show the cursor.
				$this.terminal.write('\x1B[?25h');

				UpdateTerminalFit();
			}
			else if (str === 'interactive_echo')
			{
				// For most real-world scenarios, this is probably the wrong mode.  See 'interactive'.
				elems.readlinewrap.classList.add('ext-run-process-hidden');
				DisableSecureInput();

				echoinput = true;

				// Show the cursor.
				$this.terminal.write('\x1B[?25h');

				UpdateTerminalFit();
			}
			else if (str === 'readline')
			{
				elems.readlinewrap.classList.remove('ext-run-process-hidden');
				DisableSecureInput();

				echoinput = false;

				// Hide the cursor.
				$this.terminal.write('\x1B[?25l');

				UpdateTerminalFit();
			}
			else if (str === 'readline_secure')
			{
				elems.readlinewrap.classList.remove('ext-run-process-hidden');
				EnableSecureInput();

				echoinput = false;

				// Hide the cursor.
				$this.terminal.write('\x1B[?25l');

				UpdateTerminalFit();
			}
			else if (str === 'none')
			{
				// Disables obvious input.  Hidden input may still be sent until stdin is closed.
				elems.readlinewrap.classList.add('ext-run-process-hidden');
				DisableSecureInput();

				echoinput = false;

				// Hide the cursor.
				$this.terminal.write('\x1B[?25l');

				UpdateTerminalFit();
			}

			if (hasfocus)  $this.terminal.focus();

			return true;
		};

		$this.terminal.addOscHandler(1000, CustomOSCHandler);

		// Button click callbacks.
		var AttachedDetachedClickHandler = function(e) {
			e.preventDefault();

			// Hide both buttons and remove the handler.  This allows for time to synchronize with a host without a user trying to alter the attach state again.
			elems.button_attached.classList.add('ext-run-process-hidden');
			elems.button_detached.classList.add('ext-run-process-hidden');

			var func = $this.settings.attachedbutton;
			$this.settings.attachedbutton = null;

			if (func)  func.call($this, e);
		};

		elems.button_attached.addEventListener('click', AttachedDetachedClickHandler);
		elems.button_detached.addEventListener('click', AttachedDetachedClickHandler);

		var TerminateClickHandler = function(e) {
			e.preventDefault();

			if ($this.settings.connected && $this.settings.running && $this.settings.terminatebutton && confirm(Translate('Are you sure you want to terminate this process?')))
			{
				elems.button_terminate.classList.add('ext-run-process-hidden');

				var func = $this.settings.terminatebutton;
				$this.settings.terminatebutton = null;

				func.call($this, e);
			}
		};

		elems.button_terminate.addEventListener('click', TerminateClickHandler);

		var RemoveClickHandler = function(e) {
			e.preventDefault();

			if ($this.settings.removebutton)  $this.settings.removebutton.call($this, e);
		};

		elems.button_remove.addEventListener('click', RemoveClickHandler);


		// Public functions.

		$this.SetHistory = function(newhistory) {
			history = [];

			if (typeof(newhistory) === 'object')
			{
				newhistory.forEach(function(line) {
					if (typeof(line) === 'string' && line !== '')  history.push([line, true]);
				});
			}

			history.push(['', false]);
			historypos = history.length - 1;
		};

		$this.SettingsUpdated = function(updateall) {
			// Temporarily disable the UpdateTerminalFit() function.
			allowterminalfit = false;

			// Update status icons.
			if ($this.settings.connected)
			{
				elems.status_disconnected.classList.add('ext-run-process-hidden');

				if (elems.terminalwrap.classList.contains('ext-run-process-disconnected'))
				{
					elems.terminalwrap.classList.remove('ext-run-process-disconnected');

					updateall = true;
				}
			}
			else
			{
				elems.status_disconnected.classList.remove('ext-run-process-hidden');

				if (!elems.terminalwrap.classList.contains('ext-run-process-disconnected'))
				{
					elems.terminalwrap.classList.add('ext-run-process-disconnected');

					updateall = true;
				}
			}

			// There's no formal status icon for stdin but plenty of other UI indicators.
			if (!$this.settings.stdinopen)
			{
				$this.terminal.setOption('disableStdin', true);

				if ($this.settings.inputmode !== 'none')
				{
					$this.settings.inputmode = 'none';

					updateall = true;
				}
			}

			if ($this.settings.running)
			{
				elems.status_running.classList.remove('ext-run-process-hidden');
				elems.status_terminated.classList.add('ext-run-process-hidden');
			}
			else
			{
				elems.status_running.classList.add('ext-run-process-hidden');
				elems.status_terminated.classList.remove('ext-run-process-hidden');

				if ($this.settings.inputmode !== 'none')
				{
					$this.settings.inputmode = 'none';

					updateall = true;
				}
			}

			if ($this.settings.hadstderr)  elems.status_hadstderr.classList.remove('ext-run-process-hidden');
			else  elems.status_hadstderr.classList.add('ext-run-process-hidden');

			// Update button list.
			if ($this.settings.attachedbutton)
			{
				if ($this.settings.attached)
				{
					elems.button_attached.classList.remove('ext-run-process-hidden');
					elems.button_detached.classList.add('ext-run-process-hidden');
				}
				else
				{
					elems.button_attached.classList.add('ext-run-process-hidden');
					elems.button_detached.classList.remove('ext-run-process-hidden');
				}
			}
			else
			{
				elems.button_attached.classList.add('ext-run-process-hidden');
				elems.button_detached.classList.add('ext-run-process-hidden');
			}

			if ($this.settings.attached)
			{
				if (elems.terminalwrap.classList.contains('ext-run-process-detached'))
				{
					elems.terminalwrap.classList.remove('ext-run-process-detached');

					updateall = true;
				}
			}
			else
			{
				if (!elems.terminalwrap.classList.contains('ext-run-process-detached'))
				{
					elems.terminalwrap.classList.add('ext-run-process-detached');

					updateall = true;
				}
			}

			if (updateall)
			{
				if ($this.settings.fullscreen)  $this.ShowFullscreen();
				else  $this.ExitFullscreen();

				if ($this.settings.fullscreenbutton)
				{
					elems.button_fullscreen.classList.remove('ext-run-process-hidden');
					elems.button_fullscreen_exit.classList.remove('ext-run-process-hidden');
				}
				else
				{
					elems.button_fullscreen.classList.add('ext-run-process-hidden');
					elems.button_fullscreen_exit.classList.add('ext-run-process-hidden');
				}
			}

			if ($this.settings.connected && $this.settings.running && $this.settings.terminatebutton)  elems.button_terminate.classList.remove('ext-run-process-hidden');
			else  elems.button_terminate.classList.add('ext-run-process-hidden');

			if ($this.settings.removebutton)  elems.button_remove.classList.remove('ext-run-process-hidden');
			else  elems.button_remove.classList.add('ext-run-process-hidden');

			allowterminalfit = true;

			// Update the input mode via the OSC handler.
			if (updateall)  $this.terminal.write('\x1B]1000;' + $this.settings.inputmode + '\x07');
		};

		$this.ShowFullscreen = function(e) {
			if (e)  e.preventDefault();

			elems.terminalwrap.classList.add('ext-run-process-terminal-fullscreen');
			document.body.classList.add('ext-run-process-fullscreen');

			if ($this.settings.fullscreenbutton)
			{
				elems.button_fullscreen.classList.add('ext-run-process-hidden');
				elems.button_fullscreen_exit.classList.remove('ext-run-process-hidden');
			}

			$this.settings.fullscreen = true;

			UpdateTerminalFit();
		};

		$this.ExitFullscreen = function(e) {
			if (e)  e.preventDefault();

			elems.terminalwrap.classList.remove('ext-run-process-terminal-fullscreen');
			document.body.classList.remove('ext-run-process-fullscreen');

			if ($this.settings.fullscreenbutton)
			{
				elems.button_fullscreen.classList.remove('ext-run-process-hidden');
				elems.button_fullscreen_exit.classList.add('ext-run-process-hidden');
			}

			$this.settings.fullscreen = false;

			UpdateTerminalFit();
		};

		elems.button_fullscreen.addEventListener('click', $this.ShowFullscreen);
		elems.button_fullscreen_exit.addEventListener('click', $this.ExitFullscreen);

		// Detach event listeners, remove DOM elements, dispose of the terminal, and clean up.
		$this.Destroy = function() {
			window.removeEventListener('resize', WindowResizeHandler);

			StopVertResize();

			elems.vertsizer.removeEventListener('mousedown', StartVertResize);
			elems.vertsizer.removeEventListener('touchstart', StartVertResize);

			elems.titlebar.removeEventListener('click', TitlebarFocusHandler);

			elems.readlinewrap.removeEventListener('click', ReadlineWrapClickHandler);
			elems.readline.removeEventListener('click', ReadlineClickHandler);
			elems.readline.removeEventListener('focus', ReadlineFocusHandler);

			elems.readline.removeEventListener('keydown', ReadlineKeydownHandler);

			elems.button_secure_input_off.removeEventListener('click', EnableSecureInput);
			elems.button_secure_input_on.removeEventListener('click', DisableSecureInput);

			$this.terminal.off('focus', TerminalFocusHandler);
			$this.terminal.off('blur', TerminalBlurHandler);

			elems.button_attached.removeEventListener('click', AttachedDetachedClickHandler);
			elems.button_detached.removeEventListener('click', AttachedDetachedClickHandler);
			elems.button_terminate.removeEventListener('click', TerminateClickHandler);
			elems.button_remove.removeEventListener('click', RemoveClickHandler);

			elems.button_fullscreen.removeEventListener('click', $this.ShowFullscreen);
			elems.button_fullscreen_exit.removeEventListener('click', $this.ExitFullscreen);

			elems.terminalwrap.classList.remove('ext-run-process-terminal-fullscreen');
			document.body.classList.remove('ext-run-process-fullscreen');

			$this.terminal.dispose();
			$this.terminal = null;

			for (var node in elems)
			{
				elems[node].parentNode.removeChild(elems[node]);
			}

			// Remaining cleanup.
			elems = null;

			$this.settings = Object.assign({}, defaults);

			$this = null;
			parentelem = null;
			options = null;
		};

		$this.SettingsUpdated(true);
	};
})();

(function() {
	var FormatStr = function(format) {
		var args = Array.prototype.slice.call(arguments, 1);

		return format.replace(/{(\d+)}/g, function(match, number) {
			return (typeof args[number] != 'undefined' ? args[number] : match);
		});
	};

	window.RunProcessSDK = function(url, authuser, authtoken) {
		if (this === window)
		{
			console.error('[RunProcessSDK] Error:  Did you forget to use the "new" keyword?');

			return;
		}

		var triggers = {};
		var ws, ready = false, queued = [], sent = {}, allowed = {}, monitoring = false, attached = {}, nextid = 1;
		var $this = this;

		// Global vars.
		$this.debug = false;

		// Internal functions.
		var DispatchEvent = function(eventname, params) {
			if (!triggers[eventname])  return;

			triggers[eventname].forEach(function(callback) {
				callback.call($this, params);
			});
		};

		var SendMessage = function(msg, callback) {
			msg.msg_id = nextid;
			if (!ready)  queued.push({ msg: msg, callback: callback });
			else
			{
				if (callback)  sent[nextid] = callback;

				if ($this.debug)  console.log('[RunProcessSDK] Sending:  ' + JSON.stringify(msg));

				ws.send(JSON.stringify(msg));
			}

			nextid++;
		};

		var WSReady = function(data) {
			ready = true;
			allowed = data.allowed;

			queued.forEach(function(info) {
				if (info.callback)  sent[info.msg.msg_id] = info.callback;

				if ($this.debug)  console.log('[RunProcessSDK] Sending:  ' + JSON.stringify(info.msg));

				ws.send(JSON.stringify(info.msg));
			});

			queued = [];
		};

		var Reconnect = function() {
			ws = new WebSocket(url);

			ws.addEventListener('open', function(e) {
				// Special initial message to unlock the WebSocket.
				var msg = {
					msg_id: nextid,
					authuser: authuser,
					authtoken: authtoken,
					action: 'test'
				};

				sent[nextid] = WSReady;
				nextid++;

				if ($this.debug)  console.log('[RunProcessSDK] Sending authentication.');

				ws.send(JSON.stringify(msg));

				DispatchEvent('connect');
			});

			ws.addEventListener('message', function(e) {
				if ($this.debug)  console.log('[RunProcessSDK] Received:  ' + e.data);

				var msg = JSON.parse(e.data);

				if (!msg.success)
				{
					console.error('[RunProcessSDK] Error:  ' + e.data);

					DispatchEvent('error', msg);
				}
				else if (typeof(msg.msg_id) !== 'undefined')
				{
					// Attempt to route the message to a callback.
					if (sent[msg.msg_id])
					{
						if ($this.debug)  console.log('[RunProcessSDK] Routing message ID ' + msg.msg_id + ' to callback');

						sent[msg.msg_id].call($this, msg);

						delete sent[msg.msg_id];
					}
				}
				else if (msg.action === 'info' && msg.monitor != '')
				{
					if (monitoring)
					{
						if (triggers['monitor'] && triggers['monitor'].length)  DispatchEvent('monitor', msg);
						else  $this.SetMonitoringMode('none');
					}
				}
				else if (attached[msg.channel] || msg.action === 'attach')
				{
					// Decode message data for certain actions.
					if (msg.action === 'stdout' || msg.action === 'stderr')  msg.data = atob(msg.data);

					DispatchEvent('message', msg);
				}
				else
				{
					if ($this.debug)  console.log('[RunProcessSDK] Unhandled message:  ' + e.data);

					DispatchEvent('unhandled', msg);
				}
			});

			var reconnecttimeout;

			ws.addEventListener('error', function(e) {
				reconnecttimeout = setTimeout(Reconnect, 1000);
			});

			ws.addEventListener('close', function(e) {
				DispatchEvent('disconnect');

				ws = null;
				ready = false;
				sent = {};
				monitoring = false;
				attached = {};

				clearTimeout(reconnecttimeout);
				setTimeout(Reconnect, 500);
			});
		};

		Reconnect();

		// Public DOM-style functions.
		$this.addEventListener = function(eventname, callback) {
			if (!triggers[eventname])  triggers[eventname] = [];

			triggers[eventname].push(callback);
		};

		$this.removeEventListener = function(eventname, callback) {
			if (!triggers[eventname])  return;

			for (var x in triggers[eventname])
			{
				if (triggers[eventname][x] === callback)
				{
					delete triggers[eventname][x];

					return;
				}
			}
		};

		// Public SDK functions.

		// Checks the status of specific actions:  clear, start, close_stdin, close_stdcmd, terminate, remove
		$this.IsAllowed = function(type) {
			return (allowed[type] ? true : false);
		};

		// Alters monitoring.
		$this.SetMonitoringMode = function(mode, callback) {
			var msg = {
				action: 'monitor',
				mode: mode
			};

			SendMessage(msg, function(data) {
				monitoring = (data.mode !== 'none');

				if (callback)  callback.call($this, data);
			});
		};

		$this.IsMonitoring = function() {
			return monitoring;
		};

		// Retrieves information for a specific channel.
		// Clears the channel of scrollback lines.
		$this.GetChannelInfo = function(channel, callback) {
			var msg = {
				action: 'get_info',
				channel: channel
			};

			SendMessage(msg, function(data) {
				if (callback)  callback.call($this, data);
			});
		};

		// Retrieves the channel list.  To avoid race conditions, start monitoring for changes before calling this function.
		$this.GetChannelList = function(tag, listcallback) {
			var msg = {
				action: 'list'
			};

			if (tag)  msg.tag = tag;

			SendMessage(msg, function(data) {
				if (listcallback)  listcallback.call($this, data);
			});
		};

		// Clears the channel of scrollback lines.
		$this.ClearChannel = function(channel, callback) {
			var msg = {
				action: 'clear',
				channel: channel
			};

			SendMessage(msg, function(data) {
				if (callback)  callback.call($this, data);
			});
		};

		// Attaches to a channel, which begins to stream lines of collected output.
		// To manually use the SDK with TerminalManager, pass TerminalManager.GetID() for the 'extra' parameter.
		$this.AttachChannel = function(channel, extra, callback) {
			var msg = {
				action: 'attach',
				channel: channel
			};

			if (extra)  msg.extra = extra;

			SendMessage(msg, function(data) {
				attached[data.channel] = true;

				if (callback)  callback.call($this, data);
			});
		};

		// Detaches from a channel that was previously attached to.
		$this.DetachChannel = function(channel, callback) {
			var msg = {
				action: 'detach',
				channel: channel
			};

			SendMessage(msg, function(data) {
				delete attached[data.channel];

				if (callback)  callback.call($this, data);
			});
		};

		// Starts a process with the specified options.  Most of the options in ProcessHelper::StartProcess() are allowed.
		// Calling this function from Javascript is generally a bad idea.  Included mostly for completeness.
		$this.StartProcess = function(tag, cmd, options, callback) {
			var msg = Object.assign({}, {
				action: 'start',
				cmd: cmd,
				tag: tag
			}, options);

			SendMessage(msg, function(data) {
				if (callback)  callback.call($this, data);
			});
		};

		// Sends Base64-encoded data to the stdin handle of the process.
		$this.SendChannelStdin = function(channel, stdindata, historylines, callback) {
			var msg = {
				action: 'send_stdin',
				channel: channel,
				data: btoa(stdindata)
			};

			if (historylines !== false)  msg.history = historylines;

			SendMessage(msg, function(data) {
				if (callback)  callback.call($this, data);
			});
		};

		// Closes stdin so it can't receive more data via SendChannelStdin().
		$this.CloseChannelStdin = function(channel, callback) {
			var msg = {
				action: 'close_stdin',
				channel: channel
			};

			SendMessage(msg, function(data) {
				if (callback)  callback.call($this, data);
			});
		};

		// Sends Base64-encoded data to the stdcmd handle of the process.
		$this.SendChannelStdcmd = function(channel, stdcmddata, callback) {
			var msg = {
				action: 'send_stdcmd',
				channel: channel,
				data: btoa(stdcmddata)
			};

			SendMessage(msg, function(data) {
				if (callback)  callback.call($this, data);
			});
		};

		// Closes stdcmd so it can't receive more data via SendChannelStdcmd().
		$this.CloseChannelStdcmd = function(channel, callback) {
			var msg = {
				action: 'close_stdcmd',
				channel: channel
			};

			SendMessage(msg, function(data) {
				if (callback)  callback.call($this, data);
			});
		};

		// Forcefully terminates a running process in a channel.  Note that this does not remove the channel as there could still be untransferred output.
		$this.TerminateChannel = function(channel, callback) {
			var msg = {
				action: 'terminate',
				channel: channel
			};

			SendMessage(msg, function(data) {
				if (callback)  callback.call($this, data);
			});
		};

		// Removes the channel even if all output has not been received.
		$this.RemoveChannel = function(channel, callback) {
			var msg = {
				action: 'remove',
				channel: channel
			};

			SendMessage(msg, function(data) {
				delete attached[data.channel];

				if (callback)  callback.call($this, data);
			});
		};
	};

	// Sets up a fully automated terminal manager that tracks each channel and routes input from and output to Xterm.js terminal windows.
	var nextmgr_id = 1;
	window.TerminalManager = function(sdk, parentelem, options) {
		if (!window.Terminal)
		{
			console.error('[RunProcessSDK::TerminalManager] Error:  Did you forget to load "xterm.js"?  Also be sure to load "addons/fit.js" so that terminals can be stretched to fit the space.');

			return;
		}

		if (this === window)
		{
			console.error('[RunProcessSDK::TerminalManager] Error:  Did you forget to use the "new" keyword?');

			return;
		}

		var terminals = {};
		var $this = this;
		var mgr_id = nextmgr_id + '_tm_js';
		nextmgr_id++;
		var allowattach = true;

		var defaults = {
			fullscreen: false,
			fullscreenbutton: true,
			autoattach: true,
			manualdetach: false,
			terminatebutton: true,
			autoremove: false,
			removebutton: true,
			initviewportheight: 0.5,
			historylines: 200,
			terminaltheme: {
				foreground: '#BBBBBB',
				cursor: '#00D600',
				cursorAccent: '#F0F0F0'
			},
			oncreate: null,
			onmessage: null,
			channel: false,
			tag: false,
			langmap: {}
		};

		var settings = Object.assign({}, defaults, options);

		// Multilingual translation.
		var Translate = function(str) {
			return (settings.langmap[str] ? settings.langmap[str] : str);
		};

		// Internal functions.
		var ConnectHandler = function() {
			// Change the connected status in existing terminals.
			for (var channel in terminals)
			{
				terminals[channel].settings.connected = true;

				terminals[channel].SettingsUpdated();
			}

			// Start monitoring for changes.
			if (!sdk.IsMonitoring())  sdk.SetMonitoringMode('important');

			// Attach to channel(s).
			if (settings.autoattach)
			{
				if (settings.channel)
				{
					if (!terminals[settings.channel] || terminals[settings.channel].attached)  sdk.AttachChannel(settings.channel, mgr_id);
				}
				else
				{
					sdk.GetChannelList(settings.tag, function(msg) {
						msg.channels.forEach(function(info) {
							// Attach terminals to tagged channels.  Also supports attaching to a single channel.
							if (info.state !== 'queued' && !info.attached && (!terminals[info.channel] || terminals[info.channel].attached) && (!settings.tag || info.tag === settings.tag))  sdk.AttachChannel(info.channel, mgr_id);
						});
					});
				}
			}
		};

		var DisconnectHandler = function() {
			// Change the connected status in existing terminals.
			for (var channel in terminals)
			{
				terminals[channel].settings.connected = false;

				terminals[channel].SettingsUpdated();
			}
		};

		// Triggering this function is generally not possible.
		var MessageErrorHandler = function(msg) {
			alert(FormatStr(Translate('An error occurred:  {0} ({1}).'), msg.error, msg.errorcode));
		};

		var MonitorHandler = function(msg) {
			// Automatically attach to channels that are attachable and match the settings.
			// NOTE:  It is not possible to get into an infinite attach/detach loop since the actual detachment during a request happens AFTER notifications,
			// which ultimately triggers the MessageHandler callback below for the channel instead of calling this function.
			if (msg.monitor === 'start' || msg.monitor === 'detach')
			{
				if ((settings.autoattach && !terminals[msg.channel]) || (terminals[msg.channel] && terminals[msg.channel].attached))
				{
					if (settings.channel)
					{
						if (settings.channel === msg.channel)  sdk.AttachChannel(msg.channel, mgr_id);
					}
					else if (!settings.tag || msg.tag === settings.tag)
					{
						sdk.AttachChannel(msg.channel, mgr_id);
					}
				}
			}
		};

		// When the manual attach/detach button is hit, it is removed from the toolbar, which allows for sufficient time to properly synchronize with the server.
		var AttachButtonToggle = function(e) {
			var channel = this.settings.context.channel;

			if (terminals[channel].settings.attached)  sdk.DetachChannel(channel);
			else  sdk.AttachChannel(channel, mgr_id);
		};

		var TerminateButtonHandler = function(e) {
			var channel = this.settings.context.channel;

			sdk.TerminateChannel(channel);
		};

		var RemoveChannel = function(channel) {
			if (!terminals[channel].settings.context.removed)  sdk.DetachChannel(channel);

//			terminals[channel].terminal.off('key', TerminalKeyHandler);
//			terminals[channel].terminal.off('data', TerminalDataHandler);
//			terminals[channel].terminal.off('resize', ResizeHandler);

			terminals[channel].settings.attachedbutton = null;
			terminals[channel].settings.terminatebutton = null;
			terminals[channel].settings.removebutton = null;
			terminals[channel].settings.onenter = null;
			terminals[channel].settings.onkey = null;
			terminals[channel].settings.ondata = null;
			terminals[channel].settings.onresize = null;

			terminals[channel].settings.context = null;

			terminals[channel].Destroy();

			delete terminals[channel];
		};

		var RemoveButtonHandler = function(e) {
			var channel = this.settings.context.channel;

			RemoveChannel(channel);

			if (settings.removebutton !== true)  settings.removebutton(e);
		};

		var ReadlineEnterHandler = function(line, secureinput) {
			var channel = this.settings.context.channel;

			if (terminals[channel].settings.stdinopen)  sdk.SendChannelStdin(channel, line, (secureinput ? false : settings.historylines));
		};

//		var TerminalKeyHandler = function(key, e) {
//			var channel = this.settings.context.channel;
//
//			if (terminals[channel].settings.stdinopen && (terminals[channel].settings.inputmode === 'interactive' || terminals[channel].settings.inputmode === 'interactive_echo'))
//			{
//				sdk.SendChannelStdin(channel, key, false);
//			}
//		};

		var TerminalDataHandler = function(data) {
			var channel = this.settings.context.channel;

			if (terminals[channel].settings.stdinopen && (terminals[channel].settings.inputmode === 'interactive' || terminals[channel].settings.inputmode === 'interactive_echo'))
			{
				sdk.SendChannelStdin(channel, data, false);
			}
		};

		// Is there an ANSI escape code sequence to also trigger a resize that doesn't rely on using the special stdcmd channel?
		// Maybe:  https://www.gnu.org/software/screen/manual/html_node/Control-Sequences.html
		var TerminalResizeHandler = function(size) {
			var channel = this.settings.context.channel;

			if (terminals[channel].settings.context.stdcmdopen)
			{
				sdk.SendChannelStdcmd(channel, JSON.stringify({ action: 'resize', size: size }) + '\n');
			}
		};

		var MessageHandler = function(msg) {
			var channel = msg.channel;

			if (msg.action === 'attach')
			{
				if (msg.attachextra !== mgr_id || !allowattach)  return;

				if (terminals[channel])
				{
					// An ExecTerminal instance already exists, just update it.
					terminals[channel].terminal.clear();
					terminals[channel].SetHistory(msg.history);

					terminals[channel].settings.attached = true;
					if (settings.manualdetach)  terminals[channel].settings.attachedbutton = AttachButtonToggle;

					terminals[channel].SettingsUpdated();
				}
				else
				{
					// Create the ExecTerminal instance.
					var options = {
						context: {
							channel: channel,
							stdcmdopen: msg.stdcmdopen,
							removed: false
						},

						stdinopen: msg.stdinopen,
						running: (msg.state === 'running'),
						hadstderr: msg.hadstderr,

						attached: msg.attached,
						fullscreen: settings.fullscreen,
						fullscreenbutton: settings.fullscreenbutton,

						attachedbutton: (settings.manualdetach ? AttachButtonToggle : null),
						terminatebutton: (settings.terminatebutton && sdk.IsAllowed('terminate') ? TerminateButtonHandler : null),
						removebutton: (settings.removebutton !== false ? RemoveButtonHandler : null),

						inittitle: (msg.extra && msg.extra.title ? msg.extra.title : FormatStr(Translate('Channel {0}, Process ID {1}'), channel, msg.realpid)),
						initviewportheight: settings.initviewportheight,

						inputmode: (msg.stdinopen ? (msg.extra && msg.extra.inputmode ? msg.extra.inputmode : 'readline') : 'none'),
						onenter: ReadlineEnterHandler,

//						onkey: TerminalKeyHandler,
						ondata: TerminalDataHandler,
						onresize: TerminalResizeHandler,

						langmap: settings.langmap
					};

					terminals[channel] = new ExecTerminal(parentelem, options);
					terminals[channel].terminal.setOption('theme', settings.terminaltheme);
					terminals[channel].SetHistory(msg.history);

					// Allow a callback to modify the terminal, attach additional handlers, etc.
					if (settings.oncreate)  settings.oncreate.call($this, msg);
				}
			}
			else if (msg.action === 'removed' && terminals[channel])
			{
				// This prevents sending additional requests to the server.
				terminals[channel].settings.context.removed = true;

				if (settings.autoremove === true || (settings.autoremove === 'keep_if_stderr' && !msg.hadstderr))  RemoveChannel(channel);
			}
			else if (!terminals[channel])
			{
				// Avoid calling onmessage when not attached.
				return;
			}

			if (terminals[channel])
			{
				if (msg.action === 'detach' && settings.manualdetach)
				{
					terminals[channel].settings.attached = false;
					terminals[channel].settings.attachedbutton = AttachButtonToggle;

					terminals[channel].SettingsUpdated();
				}
				else if (msg.action === 'clear')
				{
					terminals[channel].terminal.clear();
				}

				if (msg.action === 'stdout' || msg.action === 'stderr')
				{
					// Replace \n with \r\n for non-interactive modes.
					if (terminals[channel].settings.inputmode !== 'interactive' && terminals[channel].settings.inputmode !== 'interactive_echo')  msg.data = msg.data.replace(/\n/g, '\r\n');

					// Push the data into the terminal.
					terminals[channel].terminal.write(msg.data);
				}
				else
				{
					// Update the terminal process status.
					var updated = false;
					if (terminals[channel].settings.stdinopen && !msg.stdinopen)
					{
						terminals[channel].settings.stdinopen = false;
						updated = true;
					}

					if (terminals[channel].settings.running && msg.state !== 'running')
					{
						terminals[channel].settings.running = false;
						updated = true;
					}

					if (!terminals[channel].settings.hadstderr && msg.hadstderr)
					{
						terminals[channel].settings.hadstderr = true;
						updated = true;
					}

					if (updated)  terminals[channel].SettingsUpdated();

					terminals[channel].settings.context.stdcmdopen = msg.stdcmdopen;
				}
			}

			if (settings.onmessage)  settings.onmessage.call($this, msg);
		};

		var PreventAttaching = function(e) {
			if (!e.defaultPrevented && !e['returnValue'])  allowattach = false;
		};

		sdk.addEventListener('connect', ConnectHandler);
		sdk.addEventListener('disconnect', DisconnectHandler);
		sdk.addEventListener('error', MessageErrorHandler);
		sdk.addEventListener('monitor', MonitorHandler);
		sdk.addEventListener('message', MessageHandler);

		window.addEventListener('beforeunload', PreventAttaching);

		// Public functions.

		// Required for tracking through an AttachChannel() call.
		$this.GetID = function() {
			return mgr_id;
		}

		// Allow callbacks to get the associated SDK instance.
		$this.GetSDK = function() {
			return sdk;
		};

		// Retrieves the ExecTerminal associated with the channel.
		$this.GetExecTerminal = function(channel) {
			return terminals[channel];
		};

		// Detaches from all channels, destroys all terminals, and removes events and all DOM nodes.
		$this.Destroy = function() {
			sdk.removeEventListener('connect', ConnectHandler);
			sdk.removeEventListener('disconnect', DisconnectHandler);
			sdk.removeEventListener('error', MessageErrorHandler);
			sdk.removeEventListener('monitor', MonitorHandler);
			sdk.removeEventListener('message', MessageHandler);

			window.removeEventListener('beforeunload', PreventAttaching);

			for (var channel in terminals)
			{
				RemoveChannel(channel);
			}

			terminals = [];
		};
	};
})();
