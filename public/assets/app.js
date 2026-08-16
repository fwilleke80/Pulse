/**
 * @file app.js
 * @brief Progressive enhancement for confirmations and password fields.
 */

'use strict';

document.addEventListener('DOMContentLoaded', function ()
{
	for (const localeSelect of document.querySelectorAll('[data-language-autosubmit]'))
	{
		localeSelect.addEventListener('change', function ()
		{
			if (localeSelect.form)
			{
				localeSelect.form.submit();
			}
		});
	}

	for (const form of document.querySelectorAll('form[data-confirm]'))
	{
		form.addEventListener('submit', function (event)
		{
			if (!window.confirm(form.dataset.confirm || 'Are you sure?'))
			{
				event.preventDefault();
			}
		});
	}

	const toggle = document.getElementById('show_passwords');
	const currentPassword = document.getElementById('current_password');
	const newPassword = document.getElementById('new_password');
	const confirmPassword = document.getElementById('confirm_password');
	const warning = document.getElementById('password_mismatch_warning');

	if (toggle)
	{
		toggle.addEventListener('change', function ()
		{
			const type = toggle.checked ? 'text' : 'password';

			for (const field of [currentPassword, newPassword, confirmPassword])
			{
				if (field)
				{
					field.type = type;
				}
			}
		});
	}

	if (newPassword && confirmPassword && warning)
	{
		/** @brief Updates the password confirmation mismatch state. */
		const checkPasswords = function ()
		{
			const mismatch = newPassword.value !== confirmPassword.value && (newPassword.value !== '' || confirmPassword.value !== '');
			warning.classList.toggle('is-hidden', !mismatch);
			newPassword.classList.toggle('password-error', mismatch);
			confirmPassword.classList.toggle('password-error', mismatch);
		};

		newPassword.addEventListener('input', checkPasswords);
		confirmPassword.addEventListener('input', checkPasswords);
	}

	const cronTokenField = document.querySelector('[data-cron-token]');
	const cronTokenGenerator = document.querySelector('[data-generate-cron-token]');

	if (cronTokenField && cronTokenGenerator && window.crypto && window.crypto.getRandomValues)
	{
		/** @brief Generates a copyable web-cron token locally without retrieving the stored secret. */
		cronTokenGenerator.addEventListener('click', function ()
		{
			const bytes = new Uint8Array(32);
			window.crypto.getRandomValues(bytes);
			cronTokenField.value = Array.from(bytes, function (byte)
			{
				return byte.toString(16).padStart(2, '0');
			}).join('');
			cronTokenField.type = 'text';
			cronTokenField.focus();
			cronTokenField.select();
		});
	}

	for (const editor of document.querySelectorAll('[data-monitor-tabs]'))
	{
		const tabs = Array.from(editor.querySelectorAll('[data-tab-target]'));
		const panels = Array.from(editor.querySelectorAll('[data-tab-panel]'));
		const availableTabs = tabs.map((tab) => tab.dataset.tabTarget);
		const activeTabInput = document.querySelector('[data-active-tab-input]');

		/** @brief Activates one accessible editor tab and optionally updates browser state. */
		const activateTab = function (name, focus, updateUrl)
		{
			if (!availableTabs.includes(name))
			{
				return;
			}

			for (const tab of tabs)
			{
				const active = tab.dataset.tabTarget === name;
				tab.classList.toggle('is-active', active);
				tab.setAttribute('aria-selected', active ? 'true' : 'false');
				tab.tabIndex = active ? 0 : -1;

				if (active && focus)
				{
					tab.focus();
				}
			}

			for (const panel of panels)
			{
				const active = panel.dataset.tabPanel === name;
				panel.classList.toggle('is-active', active);
				panel.hidden = !active;
			}

			if (activeTabInput)
			{
				activeTabInput.value = name;
			}

			const saveBar = document.querySelector('[data-settings-save-bar]');

			if (saveBar)
			{
				const settingsTabs = (saveBar.dataset.settingsTabs || '').split(',');
				saveBar.hidden = !settingsTabs.includes(name);
			}

			if (updateUrl && window.history && window.URL)
			{
				const url = new URL(window.location.href);
				url.searchParams.set('tab', name);
				window.history.replaceState({}, '', url);
			}
		};

		activateTab(editor.dataset.activeTab || 'details', false, false);

		for (const [index, tab] of tabs.entries())
		{
			tab.addEventListener('click', function (event)
			{
				event.preventDefault();
				activateTab(tab.dataset.tabTarget || 'details', false, true);
			});
			tab.addEventListener('keydown', function (event)
			{
				if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key))
				{
					return;
				}

				event.preventDefault();
				let nextIndex = index;

				if (event.key === 'ArrowLeft')
				{
					nextIndex = (index - 1 + tabs.length) % tabs.length;
				}
				else if (event.key === 'ArrowRight')
				{
					nextIndex = (index + 1) % tabs.length;
				}
				else if (event.key === 'Home')
				{
					nextIndex = 0;
				}
				else if (event.key === 'End')
				{
					nextIndex = tabs.length - 1;
				}

				activateTab(tabs[nextIndex].dataset.tabTarget || 'details', true, true);
			});
		}
	}


	for (const editor of document.querySelectorAll('[data-editor-subtabs]'))
	{
		const tabs = Array.from(editor.querySelectorAll('[data-subtab-target]'));
		const panels = Array.from(editor.querySelectorAll('[data-subtab-panel]'));
		const availableTabs = tabs.map((tab) => tab.dataset.subtabTarget);
		const activeInput = editor.closest('form') ? editor.closest('form').querySelector('[data-active-subtab-input]') : null;
		const queryKey = editor.dataset.queryKey || 'section';

		/** @brief Activates one nested editor section without introducing another page-level navigation row. */
		const activateSubtab = function (name, focus, updateUrl)
		{
			if (!availableTabs.includes(name))
			{
				return;
			}

			for (const tab of tabs)
			{
				const active = tab.dataset.subtabTarget === name;
				tab.classList.toggle('is-active', active);
				tab.setAttribute('aria-selected', active ? 'true' : 'false');
				tab.tabIndex = active ? 0 : -1;

				if (active && focus)
				{
					tab.focus();
				}
			}

			for (const panel of panels)
			{
				panel.hidden = panel.dataset.subtabPanel !== name;
			}

			if (activeInput)
			{
				activeInput.value = name;
			}

			if (updateUrl && window.history && window.URL)
			{
				const url = new URL(window.location.href);
				url.searchParams.set(queryKey, name);
				window.history.replaceState({}, '', url);
			}
		};

		activateSubtab(editor.dataset.activeSubtab || availableTabs[0] || '', false, false);

		for (const [index, tab] of tabs.entries())
		{
			tab.addEventListener('click', function (event)
			{
				event.preventDefault();
				activateSubtab(tab.dataset.subtabTarget || '', false, true);
			});
			tab.addEventListener('keydown', function (event)
			{
				if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key))
				{
					return;
				}

				event.preventDefault();
				let nextIndex = index;

				if (event.key === 'ArrowLeft')
				{
					nextIndex = (index - 1 + tabs.length) % tabs.length;
				}
				else if (event.key === 'ArrowRight')
				{
					nextIndex = (index + 1) % tabs.length;
				}
				else if (event.key === 'Home')
				{
					nextIndex = 0;
				}
				else if (event.key === 'End')
				{
					nextIndex = tabs.length - 1;
				}

				activateSubtab(tabs[nextIndex].dataset.subtabTarget || '', true, true);
			});
		}
	}

	for (const expirySettings of document.querySelectorAll('[data-portal-expiry]'))
	{
		const mode = expirySettings.querySelector('[data-portal-expiry-mode]');
		const custom = expirySettings.querySelector('[data-portal-expiry-custom]');

		if (!mode || !custom)
		{
			continue;
		}

		/** @brief Shows the custom day count only when the custom expiry policy is selected. */
		const updatePortalExpiry = function ()
		{
			custom.hidden = mode.value !== 'custom';
		};

		mode.addEventListener('change', updatePortalExpiry);
		updatePortalExpiry();
	}

	for (const override of document.querySelectorAll('[data-message-override]'))
	{
		const toggle = override.querySelector('[data-message-override-toggle]');
		const fields = override.querySelector('[data-message-fields]');
		const preserveDisabledFields = override.hasAttribute('data-preserve-disabled-fields');

		if (!toggle || !fields)
		{
			continue;
		}

		/** @brief Keeps personal-message fields consistent with their enable checkbox. */
		const updateOverride = function ()
		{
			fields.classList.toggle('is-disabled', !toggle.checked);

			for (const field of fields.querySelectorAll('input, textarea'))
			{
				field.disabled = !toggle.checked && !preserveDisabledFields;
			}
		};

		toggle.addEventListener('change', updateOverride);
		updateOverride();
	}


	for (const validation of document.querySelectorAll('[data-recipient-template-validation]'))
	{
		const body = validation.querySelector('[data-recipient-template-body]');
		const warning = validation.querySelector('[data-recipient-url-warning]');
		const toggle = validation.querySelector('[data-message-override-toggle]');
		const emptyValid = validation.dataset.emptyValid === 'true';

		if (!body || !warning)
		{
			continue;
		}

		/** @brief Shows a live warning when a custom recipient body cannot provide the portal link. */
		const updateRecipientUrlWarning = function ()
		{
			const enabled = !toggle || toggle.checked;
			const value = body.value.trim();
			const missingUrl = enabled && ((value === '' && !emptyValid) || (value !== '' && !value.includes('{url}')));
			warning.hidden = !missingUrl;
		};

		body.addEventListener('input', updateRecipientUrlWarning);

		if (toggle)
		{
			toggle.addEventListener('change', updateRecipientUrlWarning);
		}

		updateRecipientUrlWarning();
	}


	for (const editor of document.querySelectorAll('[data-language-tabs]'))
	{
		const tabs = Array.from(editor.querySelectorAll('[data-language-target]'));
		const panels = Array.from(editor.querySelectorAll('[data-language-panel]'));
		const languages = tabs.map((tab) => tab.dataset.languageTarget);

		/** @brief Activates one language-specific mail-template panel. */
		const activateLanguage = function (language)
		{
			if (!languages.includes(language))
			{
				return;
			}

			for (const tab of tabs)
			{
				const active = tab.dataset.languageTarget === language;
				tab.classList.toggle('is-active', active);
				tab.setAttribute('aria-selected', active ? 'true' : 'false');
				tab.tabIndex = active ? 0 : -1;
			}

			for (const panel of panels)
			{
				const active = panel.dataset.languagePanel === language;
				panel.hidden = !active;
			}
		};

		activateLanguage(editor.dataset.activeLanguage || languages[0] || 'en');

		for (const tab of tabs)
		{
			tab.addEventListener('click', function ()
			{
				activateLanguage(tab.dataset.languageTarget || languages[0] || 'en');
			});
		}
	}

	const escalationPolicyInputs = Array.from(document.querySelectorAll('input[name="escalation_policy"]'));
	const safetyConfiguration = document.querySelector('[data-safety-configuration]');

	if (escalationPolicyInputs.length > 0 && safetyConfiguration)
	{
		/** @brief Shows safety-only configuration only when the safety-contact policy is selected. */
		const updateSafetyConfiguration = function ()
		{
			const selected = escalationPolicyInputs.find((input) => input.checked);
			safetyConfiguration.hidden = !selected || selected.value !== 'safety_contact';
		};

		for (const input of escalationPolicyInputs)
		{
			input.addEventListener('change', updateSafetyConfiguration);
		}

		updateSafetyConfiguration();
	}

	const domainSuggestions = {
		'gamil.com': 'gmail.com',
		'gmial.com': 'gmail.com',
		'gmail.con': 'gmail.com',
		'hotnail.com': 'hotmail.com',
		'hotmail.con': 'hotmail.com',
		'outlok.com': 'outlook.com',
		'outlook.con': 'outlook.com',
		'yahoo.con': 'yahoo.com',
		'icloud.con': 'icloud.com',
		'protonmail.con': 'protonmail.com'
	};

	for (const email of document.querySelectorAll('[data-contact-email]'))
	{
		const card = email.closest('[data-email-address-card]');
		const checked = card ? card.querySelector('[data-email-checked]') : null;
		const suggestion = card ? card.querySelector('[data-email-suggestion]') : null;
		const originalEmail = email.dataset.originalEmail || '';

		email.addEventListener('input', function ()
		{
			if (checked && email.value.trim() !== originalEmail)
			{
				checked.checked = false;
			}

			if (!suggestion)
			{
				return;
			}

			const parts = email.value.trim().split('@');
			const suggestedDomain = parts.length === 2 ? domainSuggestions[parts[1].toLowerCase()] : null;
			const suggestedEmail = suggestedDomain ? parts[0] + '@' + suggestedDomain : '';
			const template = suggestion.dataset.suggestionTemplate || 'Did you mean {suggestion}?';
			suggestion.textContent = suggestedEmail === '' ? '' : template.replace('{suggestion}', suggestedEmail);
			suggestion.classList.toggle('is-hidden', suggestedEmail === '');
		});
	}
});

/** @brief Positions and dismisses compact monitor row action menus. */
document.addEventListener('DOMContentLoaded', function ()
{
	const menus = Array.from(document.querySelectorAll('[data-row-action-menu]'));

	/** @brief Closes every row menu except an optional retained menu. */
	const closeMenus = function (except)
	{
		for (const menu of menus)
		{
			if (menu !== except)
			{
				menu.removeAttribute('open');
			}
		}
	};

	/** @brief Positions an open menu panel within the current viewport. */
	const positionMenu = function (menu)
	{
		const toggle = menu.querySelector('.row-action-menu-toggle');
		const panel = menu.querySelector('[data-row-action-menu-panel]');

		if (!toggle || !panel || !menu.open)
		{
			return;
		}

		panel.classList.add('is-positioned');
		panel.style.visibility = 'hidden';
		panel.style.left = '0px';
		panel.style.top = '0px';

		const toggleRect = toggle.getBoundingClientRect();
		const panelRect = panel.getBoundingClientRect();
		const gap = 6;
		const margin = 8;
		const left = Math.min(
			Math.max(margin, toggleRect.right - panelRect.width),
			Math.max(margin, window.innerWidth - panelRect.width - margin)
		);
		const fitsBelow = toggleRect.bottom + gap + panelRect.height <= window.innerHeight - margin;
		const top = fitsBelow
			? toggleRect.bottom + gap
			: Math.max(margin, toggleRect.top - panelRect.height - gap);

		panel.style.left = Math.round(left) + 'px';
		panel.style.top = Math.round(top) + 'px';
		panel.style.visibility = '';
	};

	for (const menu of menus)
	{
		menu.addEventListener('toggle', function ()
		{
			const panel = menu.querySelector('[data-row-action-menu-panel]');

			if (menu.open)
			{
				closeMenus(menu);
				positionMenu(menu);
			}
			else if (panel)
			{
				panel.classList.remove('is-positioned');
				panel.style.left = '';
				panel.style.top = '';
				panel.style.visibility = '';
			}
		});
	}

	document.addEventListener('click', function (event)
	{
		if (!(event.target instanceof Element) || !event.target.closest('[data-row-action-menu]'))
		{
			closeMenus(null);
		}
	});

	document.addEventListener('keydown', function (event)
	{
		if (event.key === 'Escape')
		{
			closeMenus(null);
		}
	});

	window.addEventListener('resize', function ()
	{
		for (const menu of menus)
		{
			positionMenu(menu);
		}
	});

	window.addEventListener('scroll', function ()
	{
		closeMenus(null);
	}, true);

	for (const editor of document.querySelectorAll('[data-markdown-editor]'))
	{
		const textarea = editor.querySelector('textarea');
		const editTab = editor.querySelector('[data-markdown-edit-tab]');
		const previewTab = editor.querySelector('[data-markdown-preview-tab]');
		const editPanel = editor.querySelector('[data-markdown-edit-panel]');
		const previewPanel = editor.querySelector('[data-markdown-preview-panel]');
		const output = editor.querySelector('[data-markdown-preview-output]');

		if (!textarea || !editTab || !previewTab || !editPanel || !previewPanel || !output)
		{
			continue;
		}

		/** @brief Switches the Markdown editor back to source editing. */
		const showEdit = function ()
		{
			editTab.classList.add('is-active');
			previewTab.classList.remove('is-active');
			editTab.setAttribute('aria-selected', 'true');
			previewTab.setAttribute('aria-selected', 'false');
			editPanel.hidden = false;
			previewPanel.hidden = true;
		};

		/** @brief Requests a server-rendered preview for the current unsaved Markdown source. */
		const showPreview = async function ()
		{
			editTab.classList.remove('is-active');
			previewTab.classList.add('is-active');
			editTab.setAttribute('aria-selected', 'false');
			previewTab.setAttribute('aria-selected', 'true');
			editPanel.hidden = true;
			previewPanel.hidden = false;
			output.textContent = editor.dataset.previewLoading || 'Rendering preview…';
			output.classList.add('is-loading');

			const form = textarea.form;
			const token = form ? form.querySelector('input[name="_csrf_token"]') : null;
			const data = new FormData();
			data.append('source', textarea.value);
			data.append('mode', editor.dataset.previewMode || 'web');

			if (token)
			{
				data.append('_csrf_token', token.value);
			}

			try
			{
				const response = await fetch(editor.dataset.previewUrl || '/markdown/preview', {
					method: 'POST',
					body: data,
					credentials: 'same-origin',
					headers: {
						'Accept': 'application/json',
					},
				});

				if (!response.ok)
				{
					throw new Error('Markdown preview request failed.');
				}

				const payload = await response.json();
				output.innerHTML = typeof payload.html === 'string' ? payload.html : '';
			}
			catch (error)
			{
				output.textContent = editor.dataset.previewError || 'Preview could not be rendered.';
			}
			finally
			{
				output.classList.remove('is-loading');
			}
		};

		editTab.addEventListener('click', showEdit);
		previewTab.addEventListener('click', showPreview);
		textarea.addEventListener('invalid', showEdit);
	}

	const documentTabUnsaved = document.querySelector('[data-document-tab-unsaved]');

	/** @brief Keeps the Documents tab warning visible when a changed card is in a hidden panel. */
	const updateDocumentTabDirtyState = function ()
	{
		if (documentTabUnsaved)
		{
			documentTabUnsaved.hidden = document.querySelector('[data-document-editor].is-dirty') === null;
		}
	};

	/** @brief Marks edited monitor documents until their individual form is saved. */
	for (const card of document.querySelectorAll('[data-document-editor]'))
	{
		const form = card.querySelector('[data-document-edit-form]');
		const indicator = card.querySelector('[data-document-unsaved-indicator]');
		const saveButton = card.querySelector('[data-document-save-button]');

		if (!form || !indicator || !saveButton)
		{
			continue;
		}

		/** @brief Captures editable values so reverting an edit also clears the warning. */
		const formSignature = function ()
		{
			return JSON.stringify(Array.from(form.elements)
				.filter(function (control)
				{
					return control instanceof HTMLInputElement
						|| control instanceof HTMLTextAreaElement
						|| control instanceof HTMLSelectElement;
				})
				.filter(function (control)
				{
					return control.name !== '' && !(control instanceof HTMLInputElement && control.type === 'hidden');
				})
				.map(function (control)
				{
					const checked = control instanceof HTMLInputElement
						&& (control.type === 'checkbox' || control.type === 'radio')
						? control.checked
						: null;

					return [control.name, control.value, checked];
				}));
		};

		const initialSignature = formSignature();

		/** @brief Synchronizes the card, badge, and save-button dirty state. */
		const updateDirtyState = function ()
		{
			const isDirty = formSignature() !== initialSignature;
			card.classList.toggle('is-dirty', isDirty);
			saveButton.classList.toggle('is-pending-save', isDirty);
			indicator.hidden = !isDirty;
			updateDocumentTabDirtyState();
		};

		form.addEventListener('input', updateDirtyState);
		form.addEventListener('change', updateDirtyState);
	}


	const monitorSettingsForm = document.querySelector('[data-monitor-settings-form]');
	const monitorMessagesForm = document.querySelector('[data-monitor-messages-form]');

	if (monitorSettingsForm && monitorMessagesForm)
	{
		const dirtyMessageSections = new Set();
		const sectionOrder = ['owner', 'recipient', 'safety', 'portal'];
		let combinedSaveInProgress = false;
		let navigationAllowed = false;

		/** @brief Marks the Messages & content subsection containing an edited control as dirty. */
		const markMessageSectionDirty = function (event)
		{
			const control = event.target;

			if (!(control instanceof Element))
			{
				return;
			}

			const panel = control.closest('[data-subtab-panel]');
			const section = panel ? panel.dataset.subtabPanel : '';

			if (sectionOrder.includes(section))
			{
				dirtyMessageSections.add(section);
			}
		};

		for (const control of monitorMessagesForm.querySelectorAll('input:not([type="hidden"]), textarea, select'))
		{
			control.addEventListener('input', markMessageSectionDirty);
			control.addEventListener('change', markMessageSectionDirty);
		}

		/** @brief Saves one dirty Messages & content subsection without leaving the editor page. */
		const saveMessageSection = async function (section)
		{
			const data = new FormData(monitorMessagesForm);
			data.set('message_section', section);
			data.set('async_save', '1');
			const response = await fetch(monitorMessagesForm.action, {
				method: 'POST',
				body: data,
				credentials: 'same-origin',
				headers: {'Accept': 'application/json'},
			});

			if (response.redirected)
			{
				navigationAllowed = true;
				window.location.assign(response.url);
				return null;
			}

			let payload = null;

			try
			{
				payload = await response.json();
			}
			catch (error)
			{
				throw new Error(monitorMessagesForm.dataset.saveError || 'Message changes could not be saved.');
			}

			if (!response.ok || !payload || payload.ok !== true)
			{
				throw new Error((payload && payload.message) || monitorMessagesForm.dataset.saveError || 'Message changes could not be saved.');
			}

			if (payload.warning)
			{
				let warningField = monitorSettingsForm.querySelector('input[name="message_save_warning"]');

				if (!warningField)
				{
					warningField = document.createElement('input');
					warningField.type = 'hidden';
					warningField.name = 'message_save_warning';
					warningField.value = '1';
					monitorSettingsForm.appendChild(warningField);
				}
			}

			return payload;
		};

		/** @brief Saves dirty message subsections first, then submits the ordinary monitor settings form. */
		monitorSettingsForm.addEventListener('submit', async function (event)
		{
			if (combinedSaveInProgress || dirtyMessageSections.size === 0)
			{
				return;
			}

			event.preventDefault();

			if (!monitorSettingsForm.reportValidity() || !monitorMessagesForm.reportValidity())
			{
				return;
			}

			const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;

			if (submitter)
			{
				submitter.disabled = true;
			}

			try
			{
				for (const section of sectionOrder)
				{
					if (!dirtyMessageSections.has(section))
					{
						continue;
					}

					const result = await saveMessageSection(section);

					if (result === null)
					{
						return;
					}
				}

				dirtyMessageSections.clear();
				combinedSaveInProgress = true;
				navigationAllowed = true;
				monitorSettingsForm.submit();
			}
			catch (error)
			{
				window.alert(error instanceof Error ? error.message : (monitorMessagesForm.dataset.saveError || 'Message changes could not be saved.'));

				if (submitter)
				{
					submitter.disabled = false;
				}
			}
		});

		/** @brief Routes Enter-key/manual message-form submission through the shared Save changes action. */
		monitorMessagesForm.addEventListener('submit', function (event)
		{
			event.preventDefault();
			monitorSettingsForm.requestSubmit();
		});

		/** @brief Warns before a real navigation would discard unsaved Messages & content edits. */
		window.addEventListener('beforeunload', function (event)
		{
			if (dirtyMessageSections.size === 0 || navigationAllowed)
			{
				return;
			}

			event.preventDefault();
			event.returnValue = '';
		});
	}

});

/** @brief Progressive enhancement for optional, one-shot check-in geolocation. */
document.addEventListener('DOMContentLoaded', function ()
{
	/** @brief Shows a non-blocking location collection or permission status. */
	const setLocationStatus = function (container, message, isError, selector)
	{
		const status = container.querySelector(selector || '[data-location-status]');

		if (!status)
		{
			return;
		}

		status.hidden = message === '';
		status.textContent = message;
		status.classList.toggle('is-error', Boolean(isError));
	};

	/** @brief Requests one current browser position without starting continuous tracking. */
	const currentPosition = function ()
	{
		return new Promise(function (resolve, reject)
		{
			if (!navigator.geolocation)
			{
				reject(new Error('Geolocation unavailable.'));
				return;
			}

			navigator.geolocation.getCurrentPosition(resolve, reject, {
				enableHighAccuracy: true,
				timeout: 12000,
				maximumAge: 60000,
			});
		});
	};

	/** @brief Produces an accuracy-appropriate address from a Nominatim result. */
	const locationLabel = function (payload, accuracy)
	{
		const address = payload && payload.address ? payload.address : {};
		const locality = address.city || address.town || address.village || address.municipality || address.county || '';
		let parts = [];

		if (accuracy <= 100)
		{
			parts = [[address.road, address.house_number].filter(Boolean).join(' '), address.postcode, locality, address.country];
		}
		else if (accuracy <= 1000)
		{
			parts = [locality, address.country];
		}
		else
		{
			parts = [address.state || locality, address.country];
		}

		parts = parts.filter(function (part, index)
		{
			return Boolean(part) && parts.indexOf(part) === index;
		});

		return (parts.join(', ') || (payload && payload.display_name) || '').slice(0, 1000);
	};

	/** @brief Reverse-geocodes one point; failures deliberately leave a coordinate-only check-in. */
	const reverseGeocode = async function (container, latitude, longitude, accuracy)
	{
		if (!container.dataset.locationGeocodeUrl)
		{
			return '';
		}

		const controller = window.AbortController ? new AbortController() : null;
		const timeout = controller ? window.setTimeout(function () { controller.abort(); }, 4000) : 0;

		try
		{
			const url = new URL(container.dataset.locationGeocodeUrl, window.location.href);
			url.searchParams.set('format', 'jsonv2');
			url.searchParams.set('addressdetails', '1');
			url.searchParams.set('zoom', '18');
			url.searchParams.set('lat', latitude.toFixed(7));
			url.searchParams.set('lon', longitude.toFixed(7));
			url.searchParams.set('accept-language', container.dataset.locationLanguage || document.documentElement.lang || 'en');
			const response = await fetch(url.toString(), {
				headers: {'Accept': 'application/json'},
				referrerPolicy: 'strict-origin-when-cross-origin',
				signal: controller ? controller.signal : undefined,
			});

			if (!response.ok)
			{
				return '';
			}

			return locationLabel(await response.json(), accuracy);
		}
		catch (error)
		{
			return '';
		}
		finally
		{
			if (timeout)
			{
				window.clearTimeout(timeout);
			}
		}
	};

	/** @brief Collects and fills one optional location payload without blocking the check-in on failure. */
	const collect = async function (container)
	{
		if (!container || container.dataset.checkInLocation !== 'true')
		{
			return null;
		}

		setLocationStatus(container, container.dataset.locationRequesting || 'Requesting location…', false);
		const available = container.querySelector('[data-location-available]');

		if (available)
		{
			available.value = '0';
		}

		try
		{
			const position = await currentPosition();
			const latitude = Number(position.coords.latitude);
			const longitude = Number(position.coords.longitude);
			const accuracy = Math.max(0.01, Number(position.coords.accuracy));
			const address = await reverseGeocode(container, latitude, longitude, accuracy);
			const values = {
				location_available: '1',
				location_latitude: latitude.toFixed(7),
				location_longitude: longitude.toFixed(7),
				location_accuracy: accuracy.toFixed(2),
				location_address: address,
			};

			for (const [name, value] of Object.entries(values))
			{
				const field = container.querySelector('[name="' + name + '"]');

				if (field)
				{
					field.value = value;
				}
			}

			setLocationStatus(container, container.dataset.locationRecorded || 'Location will be recorded.', false);
			return values;
		}
		catch (error)
		{
			const denied = error && (error.code === 1 || error.name === 'NotAllowedError');
			setLocationStatus(
				container,
				denied ? (container.dataset.locationDenied || 'Location permission was not granted; check-in will continue without it.') : (container.dataset.locationUnavailable || 'Location is unavailable; check-in will continue without it.'),
				true
			);
			return null;
		}
	};

	window.PulseCheckInLocation = {collect: collect};

	for (const form of document.querySelectorAll('form[data-check-in-location="true"]'))
	{
		form.addEventListener('submit', async function (event)
		{
			if (form.dataset.locationCollected === 'true')
			{
				return;
			}

			event.preventDefault();
			const submitter = event.submitter instanceof HTMLButtonElement ? event.submitter : null;

			if (submitter)
			{
				submitter.disabled = true;
			}

			await collect(form);
			form.dataset.locationCollected = 'true';
			form.requestSubmit(submitter || undefined);
		});
	}

	for (const toggle of document.querySelectorAll('[data-location-recording-toggle]'))
	{
		toggle.addEventListener('change', async function ()
		{
			const settings = toggle.closest('[data-location-settings]') || toggle.parentElement.parentElement;
			const sharing = settings ? settings.querySelector('[data-location-sharing-settings]') : null;
			const shareToggle = settings ? settings.querySelector('[data-location-sharing-toggle]') : null;
			const historyLimit = settings ? settings.querySelector('[data-location-history-limit]') : null;

			if (sharing)
			{
				sharing.hidden = !toggle.checked;
			}

			if (!toggle.checked)
			{
				if (shareToggle)
				{
					shareToggle.checked = false;
				}

				if (historyLimit)
				{
					historyLimit.hidden = true;
				}

				return;
			}

			const permissionSettings = settings && settings.matches('[data-location-permission-settings]')
				? settings
				: (settings ? settings.querySelector('[data-location-permission-settings]') : null);

			if (!permissionSettings)
			{
				return;
			}

			setLocationStatus(permissionSettings, permissionSettings.dataset.locationRequesting || 'Requesting location permission…', false, '[data-location-permission-status]');

			try
			{
				await currentPosition();
				setLocationStatus(permissionSettings, permissionSettings.dataset.locationRecorded || 'Location permission was granted on this device.', false, '[data-location-permission-status]');
			}
			catch (error)
			{
				const denied = error && (error.code === 1 || error.name === 'NotAllowedError');
				setLocationStatus(permissionSettings, denied ? (permissionSettings.dataset.locationDenied || 'Location permission was not granted on this device.') : (permissionSettings.dataset.locationUnavailable || 'Location is unavailable on this device.'), true, '[data-location-permission-status]');
			}
		});
	}

	for (const toggle of document.querySelectorAll('[data-location-sharing-toggle]'))
	{
		toggle.addEventListener('change', function ()
		{
			const settings = toggle.closest('[data-location-settings]');
			const historyLimit = settings ? settings.querySelector('[data-location-history-limit]') : null;

			if (historyLimit)
			{
				historyLimit.hidden = !toggle.checked;
			}
		});
	}
});

/** @brief Closes an auxiliary preview or map tab without opening a duplicate parent page. */
document.addEventListener('DOMContentLoaded', function ()
{
	const closeButton = document.querySelector('[data-window-close]');

	if (!closeButton)
	{
		return;
	}

	closeButton.addEventListener('click', function ()
	{
		window.close();
	});
});

/** @brief Expands private document previews in place and loads framed content only on demand. */
document.addEventListener('DOMContentLoaded', function ()
{
	for (const toggle of document.querySelectorAll('[data-document-preview-toggle]'))
	{
		const card = toggle.closest('[data-document-card]');
		const mode = toggle.dataset.previewMode || 'visual';
		const panel = card ? card.querySelector('[data-document-preview-panel]') : null;
		const frame = panel ? panel.querySelector('[data-document-preview-frame]') : null;
		const loading = panel ? panel.querySelector('[data-document-preview-loading]') : null;
		let expanded = false;

		if (!card || (mode === 'frame' && (!panel || !frame)))
		{
			continue;
		}

		if (frame)
		{
			frame.addEventListener('load', function ()
			{
				panel.classList.add('is-loaded');

				if (loading)
				{
					loading.hidden = true;
				}
			});
		}

		toggle.addEventListener('click', function ()
		{
			expanded = !expanded;
			card.classList.toggle('is-expanded', expanded);
			toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			toggle.textContent = expanded
				? (toggle.dataset.hideLabel || 'Hide preview')
				: (toggle.dataset.showLabel || 'Show preview');

			if (mode !== 'frame' || !panel || !frame)
			{
				return;
			}

			panel.hidden = !expanded;

			if (expanded && frame.dataset.loaded !== 'true')
			{
				frame.dataset.loaded = 'true';
				frame.src = frame.dataset.previewSrc || '';
			}
		});
	}
});

/** @brief Reveals and renders an on-demand OpenStreetMap tile view with local Pulse overlays. */
document.addEventListener('DOMContentLoaded', function ()
{
	const map = document.querySelector('[data-interactive-location-map]');
	const mapPanel = document.querySelector('[data-map-panel]');
	const mapToggle = document.querySelector('[data-map-toggle]');

	if (!map || !mapPanel || !mapToggle)
	{
		return;
	}

	const tileLayer = map.querySelector('[data-map-tile-layer]');
	const overlay = map.querySelector('[data-map-overlay]');
	const markerLayer = map.querySelector('[data-map-marker-layer]');
	const zoomInButton = document.querySelector('[data-map-zoom-in]');
	const zoomOutButton = document.querySelector('[data-map-zoom-out]');
	const fitButton = document.querySelector('[data-map-fit]');
	const details = document.querySelector('[data-map-details]');
	const detailsHeading = details ? details.querySelector('[data-map-details-heading]') : null;
	const detailsTimestamp = details ? details.querySelector('[data-map-details-timestamp]') : null;
	const detailsAccuracy = details ? details.querySelector('[data-map-details-accuracy]') : null;
	const detailsLink = details ? details.querySelector('[data-map-details-link]') : null;
	const detailsClose = details ? details.querySelector('[data-map-details-close]') : null;
	const tileTemplate = map.dataset.mapTileUrl || '';
	const pointLabel = map.dataset.mapPointLabel || 'Check-in point';
	const tileSize = 256;
	const minimumZoom = 1;
	const maximumZoom = 19;
	const maximumFitZoom = 17;
	const earthRadius = 6378137;
	let points = [];

	try
	{
		points = JSON.parse(map.dataset.mapPoints || '[]');
	}
	catch (error)
	{
		return;
	}

	points = Array.isArray(points) ? points.filter(function (point)
	{
		const latitude = Number(point.latitude);
		const longitude = Number(point.longitude);
		return Number.isFinite(latitude)
			&& Number.isFinite(longitude)
			&& latitude >= -90
			&& latitude <= 90
			&& longitude >= -180
			&& longitude <= 180;
	}) : [];

	if (!tileLayer || !overlay || !markerLayer || points.length === 0 || tileTemplate === '')
	{
		return;
	}

	mapToggle.hidden = false;

	let zoom = 16;
	let center = {x: 0, y: 0};
	let dragging = false;
	let dragStart = {x: 0, y: 0, centerX: 0, centerY: 0};
	let resizeTimer = 0;
	let mapInitialized = false;
	const tileElements = new Map();
	const markerElements = [];

	/** @brief Projects latitude and longitude into global Web Mercator pixels. */
	const project = function (latitude, longitude, requestedZoom)
	{
		const worldSize = tileSize * Math.pow(2, requestedZoom);
		const limitedLatitude = Math.max(-85.05112878, Math.min(85.05112878, latitude));
		const radians = limitedLatitude * Math.PI / 180;
		return {
			x: (longitude + 180) / 360 * worldSize,
			y: (1 - Math.log(Math.tan(radians) + 1 / Math.cos(radians)) / Math.PI) / 2 * worldSize,
		};
	};

	/** @brief Keeps the center inside the finite Mercator world while wrapping horizontally. */
	const normalizeCenter = function ()
	{
		const worldSize = tileSize * Math.pow(2, zoom);
		center.x = ((center.x % worldSize) + worldSize) % worldSize;
		center.y = Math.max(0, Math.min(worldSize, center.y));
	};

	/** @brief Returns a projected point using the shortest horizontal wrap around the center. */
	const screenPosition = function (point)
	{
		const width = map.clientWidth;
		const height = map.clientHeight;
		const worldSize = tileSize * Math.pow(2, zoom);
		const projected = project(Number(point.latitude), Number(point.longitude), zoom);
		let differenceX = projected.x - center.x;

		if (differenceX > worldSize / 2)
		{
			differenceX -= worldSize;
		}
		else if (differenceX < -worldSize / 2)
		{
			differenceX += worldSize;
		}

		return {
			x: width / 2 + differenceX,
			y: height / 2 + projected.y - center.y,
		};
	};

	/** @brief Adds and repositions exactly the raster tiles intersecting the current viewport. */
	const renderTiles = function ()
	{
		const width = map.clientWidth;
		const height = map.clientHeight;
		const worldTileCount = Math.pow(2, zoom);
		const originX = center.x - width / 2;
		const originY = center.y - height / 2;
		const minimumTileX = Math.floor(originX / tileSize);
		const maximumTileX = Math.floor((originX + width - 1) / tileSize);
		const minimumTileY = Math.floor(originY / tileSize);
		const maximumTileY = Math.floor((originY + height - 1) / tileSize);
		const visibleKeys = new Set();

		for (let tileY = minimumTileY; tileY <= maximumTileY; ++tileY)
		{
			if (tileY < 0 || tileY >= worldTileCount)
			{
				continue;
			}

			for (let tileX = minimumTileX; tileX <= maximumTileX; ++tileX)
			{
				const wrappedTileX = ((tileX % worldTileCount) + worldTileCount) % worldTileCount;
				const key = zoom + '/' + tileX + '/' + tileY;
				visibleKeys.add(key);
				let image = tileElements.get(key);

				if (!image)
				{
					image = document.createElement('img');
					image.alt = '';
					image.width = tileSize;
					image.height = tileSize;
					image.draggable = false;
					image.decoding = 'async';
					image.referrerPolicy = 'strict-origin-when-cross-origin';
					image.src = tileTemplate
						.replace('{z}', String(zoom))
						.replace('{x}', String(wrappedTileX))
						.replace('{y}', String(tileY));
					tileLayer.appendChild(image);
					tileElements.set(key, image);
				}

				image.style.left = (tileX * tileSize - originX) + 'px';
				image.style.top = (tileY * tileSize - originY) + 'px';
			}
		}

		for (const [key, image] of tileElements.entries())
		{
			if (!visibleKeys.has(key))
			{
				image.remove();
				tileElements.delete(key);
			}
		}
	};

	/** @brief Draws the chronological path and browser-reported accuracy areas locally. */
	const renderOverlay = function ()
	{
		const width = map.clientWidth;
		const height = map.clientHeight;
		const positions = points.map(screenPosition);
		overlay.replaceChildren();
		overlay.setAttribute('viewBox', '0 0 ' + width + ' ' + height);

		for (const [index, point] of points.entries())
		{
			const latitudeRadians = Number(point.latitude) * Math.PI / 180;
			const metersPerPixel = Math.cos(latitudeRadians) * 2 * Math.PI * earthRadius
				/ (tileSize * Math.pow(2, zoom));
			const radius = Math.max(0, Number(point.accuracy_meters)) / Math.max(0.01, metersPerPixel);

			if (radius >= 2 && radius <= Math.max(width, height))
			{
				const circle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
				circle.setAttribute('cx', String(positions[index].x));
				circle.setAttribute('cy', String(positions[index].y));
				circle.setAttribute('r', String(radius));
				circle.setAttribute('class', 'portal-map-accuracy-area');
				overlay.appendChild(circle);
			}
		}

		if (positions.length > 1)
		{
			const path = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
			path.setAttribute('points', positions.map(function (position)
			{
				return position.x + ',' + position.y;
			}).join(' '));
			path.setAttribute('class', 'portal-map-path');
			overlay.appendChild(path);
		}
	};

	/** @brief Repositions the reusable numbered point buttons over the current view. */
	const renderMarkers = function ()
	{
		const width = map.clientWidth;
		const height = map.clientHeight;

		for (const [index, point] of points.entries())
		{
			const position = screenPosition(point);
			const marker = markerElements[index];
			marker.style.left = position.x + 'px';
			marker.style.top = position.y + 'px';
			marker.hidden = position.x < -40 || position.x > width + 40 || position.y < -40 || position.y > height + 40;
		}
	};

	/** @brief Refreshes tiles and local overlays for the current camera. */
	const render = function ()
	{
		normalizeCenter();
		renderTiles();
		renderOverlay();
		renderMarkers();
	};

	/** @brief Shows the metadata for one recorded check-in point without sending it elsewhere. */
	const showPoint = function (point)
	{
		if (!details || !detailsHeading || !detailsTimestamp || !detailsAccuracy || !detailsLink)
		{
			return;
		}

		detailsHeading.textContent = pointLabel + ' ' + point.number + ': ' + point.label;
		detailsTimestamp.textContent = point.timestamp;
		detailsAccuracy.textContent = point.accuracy_label;
		detailsLink.href = point.openstreetmap_url;
		details.hidden = false;
	};

	for (const point of points)
	{
		const marker = document.createElement('button');
		marker.type = 'button';
		marker.className = Number(point.number) === points.length
			? 'portal-map-marker is-last'
			: 'portal-map-marker';
		marker.textContent = String(point.number);
		marker.setAttribute('aria-label', pointLabel + ' ' + point.number + ': ' + point.label);
		marker.addEventListener('pointerdown', function (event)
		{
			event.stopPropagation();
		});
		marker.addEventListener('click', function ()
		{
			showPoint(point);
		});
		markerLayer.appendChild(marker);
		markerElements.push(marker);
	}

	/** @brief Fits every recorded point inside the viewport with a modest margin. */
	const fitPoints = function ()
	{
		const width = Math.max(280, map.clientWidth);
		const height = Math.max(320, map.clientHeight);
		const horizontalMargin = Math.min(120, width * 0.2);
		const verticalMargin = Math.min(120, height * 0.2);

		if (points.length === 1)
		{
			zoom = 16;
			center = project(Number(points[0].latitude), Number(points[0].longitude), zoom);
			render();
			return;
		}

		for (let candidateZoom = maximumFitZoom; candidateZoom >= minimumZoom; --candidateZoom)
		{
			const worldSize = tileSize * Math.pow(2, candidateZoom);
			const projected = points.map(function (point)
			{
				return project(Number(point.latitude), Number(point.longitude), candidateZoom);
			});
			const referenceX = projected[0].x;

			for (const position of projected)
			{
				while (position.x - referenceX > worldSize / 2)
				{
					position.x -= worldSize;
				}

				while (position.x - referenceX < -worldSize / 2)
				{
					position.x += worldSize;
				}
			}

			const xValues = projected.map((position) => position.x);
			const yValues = projected.map((position) => position.y);
			const minimumX = Math.min(...xValues);
			const maximumX = Math.max(...xValues);
			const minimumY = Math.min(...yValues);
			const maximumY = Math.max(...yValues);

			if (maximumX - minimumX <= width - horizontalMargin
				&& maximumY - minimumY <= height - verticalMargin)
			{
				zoom = candidateZoom;
				center = {
					x: (minimumX + maximumX) / 2,
					y: (minimumY + maximumY) / 2,
				};
				render();
				return;
			}
		}

		zoom = minimumZoom;
		const fallbackPositions = points.map(function (point)
		{
			return project(Number(point.latitude), Number(point.longitude), zoom);
		});
		const fallbackX = fallbackPositions.map((position) => position.x);
		const fallbackY = fallbackPositions.map((position) => position.y);
		center = {
			x: (Math.min(...fallbackX) + Math.max(...fallbackX)) / 2,
			y: (Math.min(...fallbackY) + Math.max(...fallbackY)) / 2,
		};
		render();
	};

	/** @brief Changes zoom around a screen-space anchor while retaining its geographic position. */
	const changeZoom = function (delta, anchorX, anchorY)
	{
		const requestedZoom = Math.max(minimumZoom, Math.min(maximumZoom, zoom + delta));

		if (requestedZoom === zoom)
		{
			return;
		}

		const width = map.clientWidth;
		const height = map.clientHeight;
		const screenX = Number.isFinite(anchorX) ? anchorX : width / 2;
		const screenY = Number.isFinite(anchorY) ? anchorY : height / 2;
		const scale = Math.pow(2, requestedZoom - zoom);
		center = {
			x: (center.x + screenX - width / 2) * scale - screenX + width / 2,
			y: (center.y + screenY - height / 2) * scale - screenY + height / 2,
		};
		zoom = requestedZoom;
		render();
	};

	map.addEventListener('pointerdown', function (event)
	{
		if (event.button !== 0)
		{
			return;
		}

		dragging = true;
		dragStart = {
			x: event.clientX,
			y: event.clientY,
			centerX: center.x,
			centerY: center.y,
		};
		map.setPointerCapture(event.pointerId);
		map.classList.add('is-dragging');
	});

	map.addEventListener('pointermove', function (event)
	{
		if (!dragging)
		{
			return;
		}

		center.x = dragStart.centerX - (event.clientX - dragStart.x);
		center.y = dragStart.centerY - (event.clientY - dragStart.y);
		render();
	});

	/** @brief Ends a pointer-driven pan gesture. */
	const stopDragging = function (event)
	{
		if (!dragging)
		{
			return;
		}

		dragging = false;
		map.classList.remove('is-dragging');

		if (map.hasPointerCapture(event.pointerId))
		{
			map.releasePointerCapture(event.pointerId);
		}
	};

	map.addEventListener('pointerup', stopDragging);
	map.addEventListener('pointercancel', stopDragging);

	map.addEventListener('wheel', function (event)
	{
		event.preventDefault();
		const bounds = map.getBoundingClientRect();
		changeZoom(event.deltaY < 0 ? 1 : -1, event.clientX - bounds.left, event.clientY - bounds.top);
	}, {passive: false});

	map.addEventListener('keydown', function (event)
	{
		const panAmount = event.shiftKey ? 180 : 80;

		if (event.key === 'ArrowLeft')
		{
			center.x -= panAmount;
		}
		else if (event.key === 'ArrowRight')
		{
			center.x += panAmount;
		}
		else if (event.key === 'ArrowUp')
		{
			center.y -= panAmount;
		}
		else if (event.key === 'ArrowDown')
		{
			center.y += panAmount;
		}
		else if (event.key === '+' || event.key === '=')
		{
			changeZoom(1);
			event.preventDefault();
			return;
		}
		else if (event.key === '-' || event.key === '_')
		{
			changeZoom(-1);
			event.preventDefault();
			return;
		}
		else if (event.key === '0' || event.key === 'Home')
		{
			fitPoints();
			event.preventDefault();
			return;
		}
		else
		{
			return;
		}

		event.preventDefault();
		render();
	});

	if (zoomInButton)
	{
		zoomInButton.addEventListener('click', function ()
		{
			changeZoom(1);
		});
	}

	if (zoomOutButton)
	{
		zoomOutButton.addEventListener('click', function ()
		{
			changeZoom(-1);
		});
	}

	if (fitButton)
	{
		fitButton.addEventListener('click', fitPoints);
	}

	if (detailsClose && details)
	{
		detailsClose.addEventListener('click', function ()
		{
			details.hidden = true;
		});
	}

	window.addEventListener('resize', function ()
	{
		if (!mapInitialized || mapPanel.hidden)
		{
			return;
		}

		window.clearTimeout(resizeTimer);
		resizeTimer = window.setTimeout(render, 120);
	});

	mapToggle.addEventListener('click', function ()
	{
		const showMap = mapPanel.hidden;
		mapPanel.hidden = !showMap;
		mapToggle.setAttribute('aria-expanded', showMap ? 'true' : 'false');
		mapToggle.textContent = showMap
			? (mapToggle.dataset.mapHideLabel || 'Hide map')
			: (mapToggle.dataset.mapShowLabel || 'Show locations on map');

		if (!showMap)
		{
			mapToggle.focus();
			return;
		}

		window.requestAnimationFrame(function ()
		{
			if (mapInitialized)
			{
				render();
			}
			else
			{
				mapInitialized = true;
				fitPoints();
			}

			map.focus();
		});
	});
});

/** @brief Renders TOTP enrollment QR codes and supports one-time recovery-code copying locally. */
document.addEventListener('DOMContentLoaded', function ()
{
	for (const canvas of document.querySelectorAll('[data-totp-qr-code]'))
	{
		const value = canvas.dataset.totpUri || '';

		if (value === '' || typeof qrcodegen === 'undefined')
		{
			continue;
		}

		try
		{
			const qr = qrcodegen.QrCode.encodeText(value, qrcodegen.QrCode.Ecc.MEDIUM);
			const border = 4;
			const scale = 6;
			const size = (qr.size + border * 2) * scale;
			const context = canvas.getContext('2d');

			if (!context)
			{
				continue;
			}

			canvas.width = size;
			canvas.height = size;
			context.imageSmoothingEnabled = false;
			context.fillStyle = '#ffffff';
			context.fillRect(0, 0, size, size);
			context.fillStyle = '#000000';

			for (let y = 0; y < qr.size; y++)
			{
				for (let x = 0; x < qr.size; x++)
				{
					if (qr.getModule(x, y))
					{
						context.fillRect((x + border) * scale, (y + border) * scale, scale, scale);
					}
				}
			}
		}
		catch (error)
		{
			canvas.hidden = true;
		}
	}

	const copyButton = document.querySelector('[data-copy-totp-recovery-codes]');

	if (copyButton)
	{
		copyButton.addEventListener('click', async function ()
		{
			const codes = Array.from(document.querySelectorAll('[data-totp-recovery-code-list] code'))
				.map(function (node) { return node.textContent ? node.textContent.trim() : ''; })
				.filter(Boolean);
			const status = document.querySelector('[data-totp-recovery-copy-status]');

			try
			{
				await navigator.clipboard.writeText(codes.join('\n'));

				if (status)
				{
					status.textContent = copyButton.dataset.copySuccess || '';
					status.hidden = false;
				}
			}
			catch (error)
			{
				const selection = window.getSelection();
				const list = document.querySelector('[data-totp-recovery-code-list]');

				if (selection && list)
				{
					const range = document.createRange();
					range.selectNodeContents(list);
					selection.removeAllRanges();
					selection.addRange(range);
				}
			}
		});
	}
});

/** @brief Progressive enhancement for WebAuthn passkey registration, login, and quick check-in. */
document.addEventListener('DOMContentLoaded', function ()
{
	/** @brief Decodes a base64url string into a browser BufferSource. */
	const base64UrlToBytes = function (value)
	{
		const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
		const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
		const binary = window.atob(padded);
		const bytes = new Uint8Array(binary.length);

		for (let index = 0; index < binary.length; ++index)
		{
			bytes[index] = binary.charCodeAt(index);
		}

		return bytes;
	};

	/** @brief Encodes an ArrayBuffer as unpadded base64url. */
	const bytesToBase64Url = function (value)
	{
		const bytes = new Uint8Array(value);
		let binary = '';

		for (const byte of bytes)
		{
			binary += String.fromCharCode(byte);
		}

		return window.btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
	};

	/** @brief Converts JSON WebAuthn creation options to browser BufferSource values. */
	const creationOptions = function (options)
	{
		options.challenge = base64UrlToBytes(options.challenge);
		options.user.id = base64UrlToBytes(options.user.id);
		options.excludeCredentials = (options.excludeCredentials || []).map(function (credential)
		{
			return Object.assign({}, credential, {id: base64UrlToBytes(credential.id)});
		});
		return options;
	};

	/** @brief Converts JSON WebAuthn request options to browser BufferSource values. */
	const requestOptions = function (options)
	{
		options.challenge = base64UrlToBytes(options.challenge);

		if (Array.isArray(options.allowCredentials))
		{
			options.allowCredentials = options.allowCredentials.map(function (credential)
			{
				return Object.assign({}, credential, {id: base64UrlToBytes(credential.id)});
			});
		}

		return options;
	};

	/** @brief Copies the current CSRF token into an API form payload. */
	const appendCsrf = function (form, data)
	{
		const token = form.querySelector('input[name="_csrf_token"]');

		if (token)
		{
			data.append('_csrf_token', token.value);
		}
	};

	/** @brief Sends form data and returns a decoded JSON response or throws its message. */
	const postJson = async function (url, data)
	{
		const response = await fetch(url, {
			method: 'POST',
			body: data,
			credentials: 'same-origin',
			headers: {'Accept': 'application/json'},
		});
		const payload = await response.json().catch(function ()
		{
			return {};
		});

		if (!response.ok)
		{
			throw new Error(typeof payload.message === 'string' ? payload.message : 'Passkey request failed.');
		}

		return payload;
	};

	/** @brief Shows one passkey operation status beside its controls. */
	const setStatus = function (form, message, isError)
	{
		const status = form.querySelector('[data-passkey-status]');

		if (!status)
		{
			return;
		}

		status.hidden = message === '';
		status.textContent = message;
		status.classList.toggle('is-error', Boolean(isError));
	};

	/** @brief Returns whether this browser can perform WebAuthn ceremonies. */
	const passkeysAvailable = function ()
	{
		return Boolean(window.PublicKeyCredential && navigator.credentials && navigator.credentials.create && navigator.credentials.get);
	};

	for (const form of document.querySelectorAll('[data-passkey-register-form]'))
	{
		const button = form.querySelector('[data-passkey-register]');

		if (!button)
		{
			continue;
		}

		button.addEventListener('click', async function ()
		{
			setStatus(form, '', false);

			if (!passkeysAvailable())
			{
				setStatus(form, form.dataset.passkeyUnavailable || 'Passkeys are unavailable in this browser.', true);
				return;
			}

			if (!form.reportValidity())
			{
				return;
			}

			button.disabled = true;

			try
			{
				const beginData = new FormData(form);
				const begin = await postJson(form.dataset.passkeyOptionsUrl || '', beginData);
				const credential = await navigator.credentials.create({publicKey: creationOptions(begin.publicKey)});

				if (!(credential instanceof PublicKeyCredential))
				{
					throw new Error(form.dataset.passkeyCancelled || 'Passkey creation was cancelled.');
				}

				const verifyData = new FormData();
				appendCsrf(form, verifyData);
				verifyData.append('credential_id', bytesToBase64Url(credential.rawId));
				verifyData.append('client_data_json', bytesToBase64Url(credential.response.clientDataJSON));
				verifyData.append('attestation_object', bytesToBase64Url(credential.response.attestationObject));
				verifyData.append('transports', typeof credential.response.getTransports === 'function' ? credential.response.getTransports().join(',') : '');
				const verified = await postJson(form.dataset.passkeyVerifyUrl || '', verifyData);

				if (verified.reload)
				{
					window.location.reload();
				}
				else if (verified.redirect)
				{
					window.location.assign(verified.redirect);
				}
			}
			catch (error)
			{
				const cancelled = error instanceof DOMException && ['NotAllowedError', 'AbortError'].includes(error.name);
				const alreadyAvailable = error instanceof DOMException && error.name === 'InvalidStateError';

				if (alreadyAvailable)
				{
					setStatus(form, form.dataset.passkeyAlreadyAvailable || 'A Pulse passkey is already available on this device.', true);
				}
				else
				{
					setStatus(form, cancelled ? (form.dataset.passkeyCancelled || error.message) : error.message, true);
				}
			}
			finally
			{
				button.disabled = false;
			}
		});
	}

	/** @brief Prevents a native password submission while passkey login owns the authentication flow. */
	const setPasskeyLoginBusy = function (container, busy)
	{
		const scope = container.closest('[data-passkey-login-scope]');

		if (!scope)
		{
			return;
		}

		scope.dataset.passkeyCeremonyActive = busy ? 'true' : 'false';
		const passwordForm = scope.querySelector('[data-password-login-form]');
		const passwordSubmit = passwordForm ? passwordForm.querySelector('button[type="submit"]') : null;

		if (passwordSubmit)
		{
			passwordSubmit.disabled = busy;
		}
	};

	for (const scope of document.querySelectorAll('[data-passkey-login-scope]'))
	{
		const passwordForm = scope.querySelector('[data-password-login-form]');

		if (passwordForm)
		{
			passwordForm.addEventListener('submit', function (event)
			{
				if (scope.dataset.passkeyCeremonyActive === 'true')
				{
					event.preventDefault();
					event.stopImmediatePropagation();
				}
			}, true);
		}
	}

	/** @brief Runs a passkey assertion for a login/quick-check-in form. */
	const authenticate = async function (form, button)
	{
		setStatus(form, '', false);

		if (!passkeysAvailable())
		{
			setStatus(form, form.dataset.passkeyUnavailable || 'Passkeys are unavailable in this browser.', true);
			return;
		}

		button.disabled = true;
		setPasskeyLoginBusy(form, true);
		let redirecting = false;

		try
		{
			const beginData = new FormData();
			appendCsrf(form, beginData);
			const begin = await postJson(form.dataset.passkeyOptionsUrl || '', beginData);
			const credential = await navigator.credentials.get({publicKey: requestOptions(begin.publicKey)});

			if (!(credential instanceof PublicKeyCredential))
			{
				throw new Error(form.dataset.passkeyCancelled || 'Passkey authentication was cancelled.');
			}

			const verifyData = new FormData();
			appendCsrf(form, verifyData);
			verifyData.append('credential_id', bytesToBase64Url(credential.rawId));
			verifyData.append('client_data_json', bytesToBase64Url(credential.response.clientDataJSON));
			verifyData.append('authenticator_data', bytesToBase64Url(credential.response.authenticatorData));
			verifyData.append('signature', bytesToBase64Url(credential.response.signature));
			verifyData.append('user_handle', credential.response.userHandle ? bytesToBase64Url(credential.response.userHandle) : '');
			const location = window.PulseCheckInLocation ? await window.PulseCheckInLocation.collect(form) : null;

			if (location)
			{
				for (const [name, value] of Object.entries(location))
				{
					verifyData.append(name, value);
				}
			}
			const verified = await postJson(form.dataset.passkeyVerifyUrl || '', verifyData);

			if (verified.redirect)
			{
				redirecting = true;
				window.location.replace(verified.redirect);
			}
		}
		catch (error)
		{
			const cancelled = error instanceof DOMException && ['NotAllowedError', 'AbortError'].includes(error.name);
			setStatus(form, cancelled ? (form.dataset.passkeyCancelled || error.message) : error.message, true);
		}
		finally
		{
			if (!redirecting)
			{
				button.disabled = false;
				setPasskeyLoginBusy(form, false);
			}
		}
	};

	for (const form of document.querySelectorAll('[data-passkey-login-form]'))
	{
		const button = form.querySelector('[data-passkey-login]');

		if (button)
		{
			button.addEventListener('click', function ()
			{
				authenticate(form, button);
			});
		}
	}

	for (const form of document.querySelectorAll('[data-quick-checkin-form]'))
	{
		const button = form.querySelector('[data-quick-passkey]');

		if (button)
		{
			button.addEventListener('click', function ()
			{
				authenticate(form, button);
			});
		}
	}
});
