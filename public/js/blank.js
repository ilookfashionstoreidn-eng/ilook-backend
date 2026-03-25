document.addEventListener("DOMContentLoaded", function () {
    var menuItems = document.querySelectorAll(".menu-item");
    var blankLabel = document.getElementById("blankLabel");
    var statusButton = document.getElementById("statusButton");
    var statusMenu = document.getElementById("statusMenu");
    var statusOptions = document.querySelectorAll(".status-option");
    var searchAction = document.getElementById("searchAction");
    var alertAction = document.getElementById("alertAction");

    menuItems.forEach(function (item) {
        item.addEventListener("click", function () {
            menuItems.forEach(function (node) {
                node.classList.remove("active");
            });
            item.classList.add("active");

            var label = item.textContent ? item.textContent.trim() : "Blank";
            if (blankLabel) {
                blankLabel.textContent = "Blank Content - " + label;
            }
        });
    });

    if (statusButton && statusMenu) {
        statusButton.addEventListener("click", function (event) {
            event.stopPropagation();
            statusMenu.classList.toggle("open");
        });
    }

    statusOptions.forEach(function (option) {
        option.addEventListener("click", function () {
            var nextStatus = option.getAttribute("data-status") || "Status";
            if (statusButton) {
                statusButton.textContent = nextStatus;
            }
            if (blankLabel) {
                blankLabel.textContent = "Blank Content - " + nextStatus;
            }
            if (statusMenu) {
                statusMenu.classList.remove("open");
            }
        });
    });

    if (searchAction) {
        searchAction.addEventListener("click", function () {
            if (blankLabel) {
                blankLabel.textContent = "Search clicked";
            }
        });
    }

    if (alertAction) {
        alertAction.addEventListener("click", function () {
            if (blankLabel) {
                blankLabel.textContent = "Alert clicked";
            }
        });
    }

    document.addEventListener("click", function (event) {
        if (!statusMenu || !statusButton) {
            return;
        }
        var clickedInsideMenu = statusMenu.contains(event.target);
        var clickedButton = statusButton.contains(event.target);
        if (!clickedInsideMenu && !clickedButton) {
            statusMenu.classList.remove("open");
        }
    });
});
