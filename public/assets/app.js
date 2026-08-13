/**
 * @file app.js
 * @brief Progressive enhancement for confirmations and password fields.
 */

'use strict';

document.addEventListener('DOMContentLoaded', function ()
{
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

			if (updateUrl && window.history && window.URL)
			{
				const url = new URL(window.location.href);
				url.searchParams.set('tab', name);
				window.history.replaceState({}, '', url);
			}
		};

		activateTab(editor.dataset.activeTab || 'schedule', false, false);

		for (const [index, tab] of tabs.entries())
		{
			tab.addEventListener('click', function (event)
			{
				event.preventDefault();
				activateTab(tab.dataset.tabTarget || 'schedule', false, true);
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

				activateTab(tabs[nextIndex].dataset.tabTarget || 'schedule', true, true);
			});
		}
	}

	for (const override of document.querySelectorAll('[data-message-override]'))
	{
		const toggle = override.querySelector('[data-message-override-toggle]');
		const fields = override.querySelector('[data-message-fields]');

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
				field.disabled = !toggle.checked;
			}
		};

		toggle.addEventListener('change', updateOverride);
		updateOverride();
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
		const form = email.closest('form');
		const checked = form ? form.querySelector('[data-email-checked]') : null;
		const suggestion = form ? form.querySelector('[data-email-suggestion]') : null;
		const originalEmail = email.dataset.originalEmail || '';

		email.addEventListener('input', function ()
		{
			if (checked && originalEmail !== '' && email.value.trim() !== originalEmail)
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
