(() => {
	'use strict';

	const app = document.querySelector('[data-schedule-app]');
	if (!app) return;

	const rowsRoots = new Map(
		Array.from(app.querySelectorAll('[data-schedule-rows]')).map((root) => [root.dataset.scheduleRows, root])
	);
	const playerSelect = app.querySelector('[data-player-select]');
	const feedState = app.querySelector('[data-feed-state]');
	const updated = app.querySelector('[data-updated]');
	const sourceStatus = app.querySelector('[data-source-status]');
	const sourceCopy = app.querySelector('[data-source-copy]');
	const nextMatch = app.querySelector('[data-next-match]');
	const toast = app.querySelector('[data-toast]');
	let activeFilter = 'all';
	let latestSchedule = [];
	let currentNextMatch = null;
	let toastTimer = 0;

	const element = (tag, className, value) => {
		const node = document.createElement(tag);
		if (className) node.className = className;
		if (value !== undefined && value !== null) node.textContent = String(value);
		return node;
	};

	const matchState = (match) => {
		if (match?.state === 'live' || match?.live) return 'live';
		if (match?.state === 'result' || match?.completed) return 'result';
		return 'upcoming';
	};

	const formatAt = (date, timeZone, suffix) => {
		const parts = Object.fromEntries(
			new Intl.DateTimeFormat('en-GB', {
				timeZone,
				weekday: 'short',
				day: 'numeric',
				month: 'short',
				hour: 'numeric',
				minute: '2-digit',
				hour12: true,
			}).formatToParts(date).map((part) => [part.type, part.value])
		);
		return `${parts.weekday} ${parts.day} ${parts.month} · ${parts.hour}:${parts.minute} ${String(parts.dayPeriod || '').toLowerCase()} ${suffix}`;
	};

	const formatMatchTime = (value) => {
		if (!value) return 'Date & time TBC';
		const date = new Date(value);
		if (Number.isNaN(date.getTime())) return 'Date & time TBC';
		return `${formatAt(date, 'America/New_York', 'ET')} / ${formatAt(date, 'Europe/London', 'UK')}`;
	};

	const formatSourceTime = (value) => {
		const date = new Date(value);
		if (!value || Number.isNaN(date.getTime())) return '';
		return new Intl.DateTimeFormat('en-GB', {
			timeZone: 'Europe/London',
			day: 'numeric',
			month: 'short',
			hour: '2-digit',
			minute: '2-digit',
		}).format(date).replace(',', ' · ');
	};

	const showToast = (message) => {
		if (!toast) return;
		window.clearTimeout(toastTimer);
		toast.textContent = message;
		toast.hidden = false;
		toastTimer = window.setTimeout(() => {
			toast.hidden = true;
		}, 2200);
	};

	const updateSourceStatus = (source = {}) => {
		if (!sourceStatus || !sourceCopy) return;
		const ok = source.ok === true || source.ok === 1 || source.ok === '1';
		const lastSuccess = source.last_success_at || sourceStatus.dataset.sourceLastSuccess || '';
		sourceStatus.dataset.sourceOk = ok ? '1' : '0';
		sourceStatus.dataset.sourceLastSuccess = lastSuccess;
		sourceStatus.classList.toggle('has-warning', !ok);
		if (lastSuccess) {
			sourceCopy.textContent = `${ok ? 'Last successful update' : 'Last success'} ${formatSourceTime(lastSuccess)} UK`;
		} else {
			sourceCopy.textContent = ok ? 'DigitalPool connected' : 'Waiting for first successful update';
		}
	};

	const updateTimes = () => {
		app.querySelectorAll('[data-match-time]').forEach((time) => {
			const row = time.closest('[data-scheduled-time]');
			if (row) time.textContent = formatMatchTime(row.dataset.scheduledTime);
		});
	};

	const countdownCopy = (scheduledTime) => {
		const remaining = new Date(scheduledTime).getTime() - Date.now();
		if (remaining <= 0) return 'Starting now';
		const totalSeconds = Math.floor(remaining / 1000);
		const days = Math.floor(totalSeconds / 86400);
		const hours = Math.floor((totalSeconds % 86400) / 3600);
		const minutes = Math.floor((totalSeconds % 3600) / 60);
		const seconds = totalSeconds % 60;
		return days > 0
			? `${days}d ${String(hours).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m`
			: `${String(hours).padStart(2, '0')}h ${String(minutes).padStart(2, '0')}m ${String(seconds).padStart(2, '0')}s`;
	};

	const renderNextMatch = () => {
		if (!nextMatch) return;
		const now = Date.now();
		const upcoming = latestSchedule
			.filter((match) => matchState(match) === 'upcoming')
			.filter((match) => activeFilter === 'all' || match.player_slug === activeFilter)
			.filter((match) => match.scheduled_time && new Date(match.scheduled_time).getTime() > now)
			.sort((a, b) => new Date(a.scheduled_time).getTime() - new Date(b.scheduled_time).getTime());
		currentNextMatch = upcoming[0] || null;
		if (!currentNextMatch) {
			nextMatch.hidden = true;
			return;
		}
		nextMatch.hidden = false;
		nextMatch.querySelector('[data-next-countdown]').textContent = countdownCopy(currentNextMatch.scheduled_time);
		nextMatch.querySelector('[data-next-player]').textContent = currentNextMatch.tracked_player || 'Player';
		nextMatch.querySelector('[data-next-opponent]').textContent = `vs ${currentNextMatch.opponent_name || 'Opponent TBC'}`;
		nextMatch.querySelector('[data-next-time]').textContent = formatMatchTime(currentNextMatch.scheduled_time);
		nextMatch.querySelector('[data-next-table]').textContent = currentNextMatch.table || 'Table TBC';
	};

	const applyFilter = () => {
		app.querySelectorAll('[data-schedule-section]').forEach((section) => {
			const rows = Array.from(section.querySelectorAll('[data-player]'));
			let visible = 0;
			rows.forEach((row) => {
				const show = activeFilter === 'all' || row.dataset.player === activeFilter;
				row.hidden = !show;
				if (show) visible += 1;
			});

			const empty = section.querySelector('[data-section-empty]');
			const count = section.querySelector('[data-section-count]');
			const hideLiveSection = section.dataset.scheduleSection === 'live' && visible === 0;
			section.hidden = hideLiveSection;
			if (empty) empty.hidden = hideLiveSection || visible !== 0;
			if (count) count.textContent = String(visible);
		});
		renderNextMatch();
	};

	const setFilter = (value) => {
		activeFilter = value || 'all';
		app.querySelectorAll('[data-filter]').forEach((button) => {
			button.setAttribute('aria-pressed', String(button.dataset.filter === activeFilter));
		});
		if (playerSelect && playerSelect.value !== activeFilter) playerSelect.value = activeFilter;
		applyFilter();
	};

	const calendarButton = () => {
		const button = element('button', 'upusa-calendar-button', 'Live calendar');
		button.type = 'button';
		button.dataset.addCalendar = '';
		return button;
	};

	const buildRow = (match) => {
		const state = matchState(match);
		const row = element('article', `upusa-match${state === 'live' ? ' is-live' : ''}`);
		row.dataset.player = match.player_slug || '';
		row.dataset.matchState = state;
		row.dataset.scheduledTime = match.scheduled_time || '';
		row.dataset.playerName = match.tracked_player || 'Player';
		row.dataset.opponentName = match.opponent_name || 'Opponent TBC';
		row.dataset.tournament = match.tournament || 'Competition';
		row.dataset.round = match.round || 'Round TBC';
		row.dataset.table = match.table || 'Table TBC';

		const timeGroup = element('div', 'upusa-match-time');
		const time = element('time', '', formatMatchTime(match.scheduled_time));
		time.dataset.matchTime = '';
		if (match.scheduled_time) time.dateTime = String(match.scheduled_time);
		timeGroup.append(time);
		if (state === 'live') timeGroup.append(element('span', '', 'Live'));

		const player = element('div', 'upusa-match-player');
		player.append(element('strong', '', match.tracked_player || 'Player'));

		const detail = element('div', 'upusa-match-detail');
		const copy = element('div', 'upusa-match-copy');
		copy.append(
			element('strong', '', `vs ${match.opponent_name || 'Opponent TBC'}`),
			element('span', '', `${match.tournament || 'Competition'} · ${match.round || 'Round TBC'}`)
		);
		detail.append(copy);

		const showScore = state === 'live' || (state === 'result' && match.score_available !== false);
		if (showScore) {
			const outcome = state === 'live' ? 'Current' : (match.result_outcome || 'Final');
			const outcomeClass = String(outcome).toLowerCase().replace(/[^a-z0-9-]/g, '');
			const score = element('div', `upusa-inline-score is-${outcomeClass}`);
			score.append(
				element('strong', '', `${Number(match.player_score) || 0} – ${Number(match.opponent_score) || 0}`),
				element('small', '', outcome)
			);
			detail.append(score);
		}

		const actions = element('div', 'upusa-match-actions');
		actions.append(element('span', 'upusa-match-table', match.table || 'Table TBC'));
		if (state === 'upcoming' && match.scheduled_time) actions.append(calendarButton());
		row.append(timeGroup, player, detail, actions);
		return row;
	};

	const render = (data) => {
		if (!Array.isArray(data?.schedule)) return;
		latestSchedule = data.schedule;
		rowsRoots.forEach((root) => root.replaceChildren());

		latestSchedule.forEach((match) => {
			const root = rowsRoots.get(matchState(match));
			if (root) root.append(buildRow(match));
		});

		if (updated) {
			const stamp = data.updated_at ? new Date(data.updated_at) : new Date();
			updated.textContent = `Updated ${stamp.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`;
		}
		updateSourceStatus(data.source || {});
		feedState?.classList.remove('is-reconnecting');
		updateTimes();
		applyFilter();
	};

	const matchFromRow = (row) => ({
		player_slug: row?.dataset.player || 'all',
		scheduled_time: row?.dataset.scheduledTime || '',
		tracked_player: row?.dataset.playerName || 'Player',
		opponent_name: row?.dataset.opponentName || 'Opponent TBC',
		tournament: row?.dataset.tournament || 'Ultimate Pool USA',
		round: row?.dataset.round || 'Match',
		table: row?.dataset.table || 'Table TBC',
	});

	const copyText = async (value) => {
		if (navigator.clipboard?.writeText) {
			await navigator.clipboard.writeText(value);
			return;
		}
		const field = document.createElement('textarea');
		field.value = value;
		field.setAttribute('readonly', '');
		field.style.position = 'fixed';
		field.style.opacity = '0';
		document.body.append(field);
		field.select();
		document.execCommand('copy');
		field.remove();
	};

	const calendarFeedUrl = (slug = 'all') => {
		const base = app.dataset.calendarBase || `${window.location.origin}/upusa-calendar`;
		return slug && slug !== 'all' ? `${base}-${encodeURIComponent(slug)}.ics` : `${base}.ics`;
	};

	const subscribeCalendar = async (slug = 'all') => {
		const url = calendarFeedUrl(slug);
		const appleDevice = /iPad|iPhone|iPod|Macintosh/i.test(navigator.userAgent);
		if (appleDevice) {
			try {
				await copyText(url);
			} catch (error) {
				// The webcal handoff still works when clipboard access is unavailable.
			}
			showToast('Opening the live calendar subscription');
			window.setTimeout(() => {
				window.location.href = url.replace(/^https?:/i, 'webcal:');
			}, 150);
			return;
		}
		try {
			await copyText(url);
			showToast('Live calendar URL copied — add it as a subscribed calendar');
		} catch (error) {
			showToast('Could not copy the live calendar URL');
		}
	};

	const shareScoreboard = async (button) => {
		const url = new URL(button.dataset.url || '/', window.location.href).href;
		const name = button.dataset.name || 'Player';
		try {
			if (typeof navigator.share === 'function') {
				await navigator.share({ title: `${name} live scoreboard`, text: `${name} live scoreboard`, url });
				return;
			}
			await copyText(url);
			showToast(`${name} scoreboard link copied`);
		} catch (error) {
			if (error?.name !== 'AbortError') showToast('Could not share this link');
		}
	};

	app.querySelectorAll('[data-filter]').forEach((button) => {
		button.addEventListener('click', () => setFilter(button.dataset.filter));
	});
	playerSelect?.addEventListener('change', () => setFilter(playerSelect.value));
	app.addEventListener('click', (event) => {
		const shareButton = event.target.closest('[data-share-scoreboard]');
		if (shareButton) {
			shareScoreboard(shareButton);
			return;
		}
		if (event.target.closest('[data-subscribe-all]')) {
			subscribeCalendar('all');
			return;
		}
		if (event.target.closest('[data-next-calendar]')) {
			if (currentNextMatch) subscribeCalendar(currentNextMatch.player_slug || 'all');
			return;
		}
		const calendar = event.target.closest('[data-add-calendar]');
		if (calendar) subscribeCalendar(matchFromRow(calendar.closest('.upusa-match')).player_slug);
	});

	const endpoint = app.dataset.endpoint;
	const refreshSeconds = Math.max(10, Math.min(60, Number(app.dataset.refresh) || 30));
	const poll = async () => {
		try {
			const controller = new AbortController();
			const timeout = window.setTimeout(() => controller.abort(), 15000);
			const response = await fetch(`${endpoint}${endpoint.includes('?') ? '&' : '?'}_=${Date.now()}`, {
				cache: 'no-store',
				credentials: 'same-origin',
				signal: controller.signal,
			});
			window.clearTimeout(timeout);
			if (!response.ok) throw new Error(`HTTP ${response.status}`);
			render(await response.json());
		} catch (error) {
			feedState?.classList.add('is-reconnecting');
			if (updated) updated.textContent = 'Reconnecting…';
		} finally {
			window.setTimeout(poll, refreshSeconds * 1000);
		}
	};

	setFilter('all');
	updateTimes();
	window.setInterval(renderNextMatch, 1000);
	window.setTimeout(poll, 250);
})();
