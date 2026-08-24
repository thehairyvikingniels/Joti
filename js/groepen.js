// js/groepen.js — Search and sort functionality for groepen.php

function tableSearch() {
  const input = document.getElementById("tableSearchInput");
  const ul = document.getElementById("tableSearchTable");
  const items = ul.getElementsByTagName("li");

  // Loop through all LI's, and hide those who don't match the search query
  for (let i = 0; i < items.length; i++) {
    const metaName = items[i].getAttribute("meta-name").toUpperCase();
    if (metaName.includes(input.value.toUpperCase())) {
      items[i].style.display = "";
    } else {
      items[i].style.display = "none";
    }
  }
}

function sortTable(metaType) {
  const table = document.getElementById("tableSearchTable");
  let switching = true;
  let i, shouldSwitch;

  while (switching) {
    switching = false;
    const rows = table.getElementsByTagName("li");

    for (i = 0; i < (rows.length - 1); i++) {
      shouldSwitch = false;
      const x = rows[i].getAttribute(metaType);
      const y = rows[i + 1].getAttribute(metaType);

      if (metaType === "meta-distance") {
        if (parseFloat(x) > parseFloat(y)) {
          shouldSwitch = true;
          break;
        }
      } else {
        if (x.toLowerCase() > y.toLowerCase()) {
          shouldSwitch = true;
          break;
        }
      }
    }
    if (shouldSwitch) {
      rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
      switching = true;
    }
  }
}
