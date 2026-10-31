(() => {
	'use strict';

	const text = (root, field, value) => {
		const element = root.querySelector(`[data-field="${field}"]`);
		if (element && value !== undefined && value !== null) {
			element.textContent = String(value);
		}
	};

	const score = (root, field, value) => {
		const element = root.querySelector(`[data-field="${field}"]`);
		if (!element || value === undefined || value === null) return;

		const next = String(value);
		if (element.textContent !== next) {
			element.textContent = next;
			element.classList.remove('typlive-score-changed');
			void element.offsetWidth;
			element.classList.add('typlive-score-changed');
		}
	};

	const render = (root, data) => {
		if (!data || !data.player || !data.opponent) return;

		text(root, 'round', data.round || data.status || 'Match');
		text(root, 'player-name', data.player.name);
		text(root, 'opponent-name', data.opponent.name);
		const raceTo = Math.max(0, Number.parseInt(data.race_to, 10) || 0);
		text(root, 'race-to', raceTo || '—');
		const race = root.querySelector('.typlive-race');
		if (race) race.setAttribute('aria-label', raceTo ? `Race to ${raceTo}` : 'Race length unavailable');
		score(root, 'player-score', data.player.score);
		score(root, 'opponent-score', data.opponent.score);
	};

	const start = (root) => {
		const endpoint = root.dataset.endpoint;
		const refreshSeconds = Math.max(3, Math.min(60, Number(root.dataset.refresh) || 5));

		const poll = async () => {
			try {
				const controller = new AbortController();
				const timeout = window.setTimeout(() => controller.abort(), 10000);
				const response = await fetch(`${endpoint}${endpoint.includes('?') ? '&' : '?'}_=${Date.now()}`, {
					cache: 'no-store',
					credentials: 'same-origin',
					signal: controller.signal,
				});
				window.clearTimeout(timeout);

				if (!response.ok) throw new Error(`HTTP ${response.status}`);
				render(root, await response.json());
			} catch (error) {
				// Keep the last valid score visible while the feed reconnects.
			} finally {
				window.setTimeout(poll, refreshSeconds * 1000);
			}
		};

		window.setTimeout(poll, 800);
	};

	document.querySelectorAll('[data-typlive-scoreboard]').forEach(start);
})();
