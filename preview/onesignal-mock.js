(() => {
	'use strict';

	let permission = false;
	const notificationListeners = new Set();
	const subscriptionListeners = new Set();
	const subscription = {
		optedIn: false,
		addEventListener: (name, callback) => {
			if (name === 'change') subscriptionListeners.add(callback);
		},
		optIn: async () => {
			subscription.optedIn = true;
			subscriptionListeners.forEach((callback) => callback({ current: { optedIn: true } }));
		},
		optOut: async () => {
			subscription.optedIn = false;
			subscriptionListeners.forEach((callback) => callback({ current: { optedIn: false } }));
		},
	};
	const oneSignal = {
		setConsentRequired: () => {},
		setConsentGiven: () => {},
		init: async () => {},
		Notifications: {
			isPushSupported: () => true,
			get permission() {
				return permission;
			},
			requestPermission: async () => {
				permission = true;
				notificationListeners.forEach((callback) => callback(true));
			},
			addEventListener: (name, callback) => {
				if (name === 'permissionChange') notificationListeners.add(callback);
			},
		},
		User: {
			PushSubscription: subscription,
			addTag: () => {},
			removeTag: () => {},
		},
	};

	window.OneSignalDeferred = {
		push: (callback) => Promise.resolve().then(() => callback(oneSignal)),
	};
})();
