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

function subscribeUserToPush() {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
    console.warn('Push messaging is not supported');
    return;
  }

  if (!window.VAPID_PUBLIC_KEY) {
    console.error('VAPID_PUBLIC_KEY is not defined.');
    return;
  }

  navigator.serviceWorker.register('/sw.js')
    .then(function(registration) {
      console.log('Service Worker registered with scope:', registration.scope);
      return navigator.serviceWorker.ready;
    })
    .then(function(registration) {
      const subscribeOptions = {
        userVisibleOnly: true,
        applicationServerKey: urlB64ToUint8Array(window.VAPID_PUBLIC_KEY)
      };

      return registration.pushManager.subscribe(subscribeOptions);
    })
    .then(function(pushSubscription) {
      console.log('Received PushSubscription:', JSON.stringify(pushSubscription));
      return sendSubscriptionToBackEnd(pushSubscription);
    })
    .catch(function(err) {
      if (Notification.permission === 'denied') {
        console.warn('Permission for notifications was denied');
      } else {
        console.error('Failed to subscribe the user: ', err);
      }
    });
}

function sendSubscriptionToBackEnd(subscription) {
  return fetch('/api/subscribe.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(subscription)
  })
  .then(function(response) {
    if (!response.ok) {
      throw new Error('Bad status code from server.');
    }
    return response.json();
  })
  .then(function(responseData) {
    if (!(responseData.data && responseData.data.success)) {
      throw new Error('Bad response from server.');
    }
  });
}

// Request permission and subscribe automatically if possible, or bind to a button if you prefer.
// For Jotify, we'll ask automatically when the script loads.
if (Notification.permission === 'default') {
  Notification.requestPermission().then((permission) => {
    if (permission === 'granted') {
      subscribeUserToPush();
    }
  });
} else if (Notification.permission === 'granted') {
  subscribeUserToPush();
}
