// Manages browser push notification permissions, Web Push subscriptions, unsubscriptions, and UI toggles.

function urlB64ToUint8Array(base64String) {
  const padding = '='.repeat((4 - base64String.length % 4) % 4);
  const base64 = (base64String + padding)
    .replace(/\-/g, '+')
    .replace(/_/g, '/');

  const rawData = window.atob(base64);
  const outputArray = new Uint8Array(rawData.length);

  for (let i = 0; i < rawData.length; ++i) {
    outputArray[i] = rawData.charCodeAt(i);
  }
  return outputArray;
}

async function getPushStatus() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    return {
      supported: false,
      permission: 'unsupported',
      subscription: null,
      isSubscribed: false
    };
  }

  const permission = Notification.permission;
  try {
    const registration = await navigator.serviceWorker.ready;
    const subscription = await registration.pushManager.getSubscription();
    return {
      supported: true,
      permission: permission,
      subscription: subscription,
      isSubscribed: Boolean(subscription)
    };
  } catch (err) {
    console.warn('Fout bij controleren push status:', err);
    return {
      supported: true,
      permission: permission,
      subscription: null,
      isSubscribed: false
    };
  }
}

async function subscribeUserToPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    throw new Error('Pushnotificaties worden niet ondersteund door deze browser.');
  }

  if (!window.VAPID_PUBLIC_KEY) {
    throw new Error('VAPID_PUBLIC_KEY is niet geconfigureerd.');
  }

  // Ensure service worker is registered and active
  await navigator.serviceWorker.register('/sw.js');
  const registration = await navigator.serviceWorker.ready;

  // Request browser permission if needed
  let permission = Notification.permission;
  if (permission === 'default') {
    permission = await Notification.requestPermission();
  }
  if (permission !== 'granted') {
    throw new Error('Toestemming voor notificaties is niet verleend.');
  }

  const subscribeOptions = {
    userVisibleOnly: true,
    applicationServerKey: urlB64ToUint8Array(window.VAPID_PUBLIC_KEY)
  };

  const subscription = await registration.pushManager.subscribe(subscribeOptions);
  await sendSubscriptionToBackEnd(subscription);
  return subscription;
}

async function unsubscribeUserFromPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    return false;
  }

  const registration = await navigator.serviceWorker.ready;
  const subscription = await registration.pushManager.getSubscription();

  if (subscription) {
    // Notify server to remove subscription from database
    try {
      await fetch('/api/subscribe.php', {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({ endpoint: subscription.endpoint })
      });
    } catch (err) {
      console.warn('Kon backend niet updaten over uitschrijven:', err);
    }

    // Unsubscribe from browser PushManager
    await subscription.unsubscribe();
  }
  return true;
}

async function sendSubscriptionToBackEnd(subscription) {
  let deviceName = 'Onbekend apparaat';
  if (navigator.userAgent) {
    const ua = navigator.userAgent;
    let browser = 'Browser';
    if (ua.includes('Firefox/')) browser = 'Firefox';
    else if (ua.includes('Edg/')) browser = 'Edge';
    else if (ua.includes('Chrome/')) browser = 'Chrome';
    else if (ua.includes('Safari/')) browser = 'Safari';

    let os = 'OS';
    if (ua.includes('Win')) os = 'Windows';
    else if (ua.includes('Mac')) os = 'macOS';
    else if (ua.includes('Linux')) os = 'Linux';
    else if (ua.includes('Android')) os = 'Android';
    else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS';

    deviceName = browser + ' op ' + os;
  }

  const payload = subscription.toJSON();
  payload.device_name = deviceName;

  const response = await fetch('/api/subscribe.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  });

  if (!response.ok) {
    throw new Error('Fout bij opslaan van notificatie-abonnement op de server.');
  }

  const responseData = await response.json();
  if (!(responseData.data && responseData.data.success)) {
    throw new Error(responseData.error || 'Server weigerde abonnement.');
  }
  return responseData;
}

async function initPushToggleButton(btnId = 'push-toggle-btn', msgId = 'push-status-msg') {
  const btn = document.getElementById(btnId);
  if (!btn) return;
  const msg = document.getElementById(msgId);

  // Set loading state while querying ServiceWorker / PushManager
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Status controleren...</span>';

  function setBtnSubscribed() {
    btn.disabled = false;
    btn.dataset.state = 'subscribed';
    btn.className = 'w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 px-4 rounded-xl text-sm transition shadow-sm flex items-center justify-center gap-2 cursor-pointer';
    btn.innerHTML = '<i class="fas fa-bell-slash"></i> <span>Pushnotificaties uitschakelen</span>';
    if (msg) {
      msg.innerHTML = '<span class="text-green-600 dark:text-green-400 font-semibold flex items-center justify-center gap-1.5"><i class="fas fa-check-circle"></i><span>Dit apparaat ontvangt pushnotificaties.</span></span>';
    }
  }

  function setBtnUnsubscribed() {
    btn.disabled = false;
    btn.dataset.state = 'unsubscribed';
    btn.className = 'w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2.5 px-4 rounded-xl text-sm transition shadow-sm flex items-center justify-center gap-2 cursor-pointer';
    btn.innerHTML = '<i class="fas fa-bell"></i> <span>Pushnotificaties inschakelen</span>';
    if (msg) {
      msg.innerHTML = '<span class="opacity-70">Dit apparaat ontvangt momenteel geen pushnotificaties.</span>';
    }
  }

  function setBtnUnsupported(message) {
    btn.disabled = true;
    btn.dataset.state = 'unsupported';
    btn.className = 'w-full bg-gray-400 text-white font-bold py-2.5 px-4 rounded-xl text-sm opacity-60 cursor-not-allowed flex items-center justify-center gap-2';
    btn.innerHTML = '<i class="fas fa-bell-slash"></i> <span>Pushnotificaties niet ondersteund</span>';
    if (msg) {
      msg.innerHTML = `<span class="text-red-500">${message || 'Je browser ondersteunt geen pushnotificaties.'}</span>`;
    }
  }

  function setBtnDenied() {
    btn.disabled = false;
    btn.dataset.state = 'denied';
    btn.className = 'w-full bg-amber-600 hover:bg-amber-700 text-white font-bold py-2.5 px-4 rounded-xl text-sm transition shadow-sm flex items-center justify-center gap-2 cursor-pointer';
    btn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>Meldingen geblokkeerd</span>';
    if (msg) {
      msg.innerHTML = '<span class="text-amber-600 dark:text-amber-400">Meldingen zijn geblokkeerd in je browser. Sta meldingen toe in je site-instellingen en klik opnieuw.</span>';
    }
  }

  // Validate browser support
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    setBtnUnsupported('Pushnotificaties worden niet ondersteund door deze browser.');
    return;
  }

  if (!window.VAPID_PUBLIC_KEY) {
    setBtnUnsupported('VAPID publieke sleutel ontbreekt in de instellingen.');
    return;
  }

  if (Notification.permission === 'denied') {
    setBtnDenied();
  } else {
    try {
      const status = await getPushStatus();
      if (status.isSubscribed) {
        setBtnSubscribed();
      } else {
        setBtnUnsubscribed();
      }
    } catch (err) {
      console.warn('Kon initiële push-status niet ophalen:', err);
      setBtnUnsubscribed();
    }
  }

  // Attach click handler for toggle
  btn.onclick = async function() {
    const currentState = btn.dataset.state;

    if (currentState === 'subscribed') {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Bezig met uitschakelen...</span>';
      try {
        await unsubscribeUserFromPush();
        setBtnUnsubscribed();
        if (msg) {
          msg.innerHTML = '<span class="text-blue-600 dark:text-blue-400 font-semibold">Pushnotificaties zijn uitgeschakeld. Pagina wordt vernieuwd...</span>';
        }
        setTimeout(() => window.location.reload(), 700);
      } catch (err) {
        console.error('Fout bij uitschrijven:', err);
        if (msg) {
          msg.innerHTML = `<span class="text-red-500 font-semibold">Fout bij uitschakelen: ${err.message || 'Onbekend'}</span>`;
        }
        setBtnSubscribed();
      }
    } else if (currentState === 'unsubscribed' || currentState === 'denied') {
      btn.disabled = true;
      btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Bezig met inschakelen...</span>';
      try {
        await subscribeUserToPush();
        setBtnSubscribed();
        if (msg) {
          msg.innerHTML = '<span class="text-green-600 dark:text-green-400 font-semibold">Pushnotificaties zijn succesvol ingeschakeld! Pagina wordt vernieuwd...</span>';
        }
        setTimeout(() => window.location.reload(), 700);
      } catch (err) {
        console.error('Fout bij inschrijven:', err);
        if (Notification.permission === 'denied') {
          setBtnDenied();
        } else {
          if (msg) {
            msg.innerHTML = `<span class="text-red-500 font-semibold">Fout bij inschakelen: ${err.message || 'Onbekend'}</span>`;
          }
          setBtnUnsubscribed();
        }
      }
    }
  };
}

// Backward compatibility helper
window.requestAndSubscribeToPush = function() {
  return subscribeUserToPush().then(() => {
    window.location.reload();
  }).catch((err) => {
    console.error('Abonneerfout:', err);
  });
};

window.getPushStatus = getPushStatus;
window.subscribeUserToPush = subscribeUserToPush;
window.unsubscribeUserFromPush = unsubscribeUserFromPush;
window.initPushToggleButton = initPushToggleButton;

// Auto-initialize toggle button on DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => initPushToggleButton('push-toggle-btn', 'push-status-msg'));
} else {
  initPushToggleButton('push-toggle-btn', 'push-status-msg');
}
