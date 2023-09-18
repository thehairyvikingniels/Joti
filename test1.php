<button onclick="notifyMe()">Notify me!</button>
<script>

function notifyMe() {
  var audio = new Audio('media/notificatie.mp3');
  audio.play();
  // Let's check if the browser supports notifications
  if (!("Notification" in window)) {
    alert("This browser does not support desktop notification");
  }

  // Let's check whether notification permissions have already been granted
  else if (Notification.permission === "granted") {
    // If it's okay let's create a notification
    var options = {
      body: "a",
      icon: "media/joti_logo.jpg",
      silent: "true"
    }
    var notification = new Notification("Hi there!",options);
  }

  // Otherwise, we need to ask the user for permission
  else if (Notification.permission !== "denied") {
    Notification.requestPermission(function (permission) {
      // If the user accepts, let's create a notification
      if (permission === "granted") {
        var notification = new Notification("Hi there!");
      }
    });
  }

  // At last, if the user has denied notifications, and you 
  // want to be respectful there is no need to bother them any more.
}

</script>
