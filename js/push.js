// Manages browser push notification permissions and registers Web Push subscriptions with the backend via VAPID keys.
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
    return Promise.reject('Push messaging is not supported');
  }

  if (!window.VAPID_PUBLIC_KEY) {
    console.error('VAPID_PUBLIC_KEY is not defined.');
    return Promise.reject('VAPID_PUBLIC_KEY is not defined.');
  }

  return navigator.serviceWorker.register('/sw.js')
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
      throw err;
    });
}

function sendSubscriptionToBackEnd(subscription) {
  let deviceName = 'Onbekend apparaat';
  if (navigator.userAgent) {
      const ua = navigator.userAgent;
      let browser = "Onbekend";
      if (ua.includes("Firefox/")) browser = "Firefox";
      else if (ua.includes("Chrome/")) browser = "Chrome";
      else if (ua.includes("Safari/")) browser = "Safari";
      else if (ua.includes("Edge/")) browser = "Edge";
      
      let os = "Onbekend OS";
      if (ua.includes("Win")) os = "Windows";
      else if (ua.includes("Mac")) os = "macOS";
      else if (ua.includes("Linux")) os = "Linux";
      else if (ua.includes("Android")) os = "Android";
      else if (ua.includes("iPhone") || ua.includes("iPad")) os = "iOS";

      deviceName = browser + " op " + os;
  }
  
  const payload = subscription.toJSON();
  payload.device_name = deviceName;

  return fetch('/api/subscribe.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
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

window.requestAndSubscribeToPush = function() {
  if (Notification.permission === 'default') {
    Notification.requestPermission().then((permission) => {
      if (permission === 'granted') {
        subscribeUserToPush().then(() => {
          window.location.reload();
        }).catch(() => {
          alert('Er is een fout opgetreden bij het abonneren.');
        });
      }
    });
  } else if (Notification.permission === 'granted') {
    subscribeUserToPush().then(() => {
      window.location.reload();
    }).catch(() => {
      alert('Er is een fout opgetreden bij het abonneren.');
    });
  } else {
    alert("Meldingen zijn geblokkeerd door je browser. Sta ze toe in je browserinstellingen.");
  }
};
