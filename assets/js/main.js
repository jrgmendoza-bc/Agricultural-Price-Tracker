/* AgriPriceTrack - shared UI behaviour */
(function () {
  "use strict";

  /* ---- Mobile sidebar (off-canvas) ---- */
  var sidebar = document.getElementById("sidebar");
  var overlay = document.getElementById("appOverlay");
  var toggler = document.getElementById("sidebarToggle");

  function closeSidebar() {
    if (!sidebar) return;
    sidebar.classList.remove("open");
    if (overlay) overlay.classList.remove("show");
  }
  function openSidebar() {
    if (!sidebar) return;
    sidebar.classList.add("open");
    if (overlay) overlay.classList.add("show");
  }
  if (toggler) {
    toggler.addEventListener("click", function () {
      sidebar.classList.contains("open") ? closeSidebar() : openSidebar();
    });
  }
  if (overlay) overlay.addEventListener("click", closeSidebar);
  document.querySelectorAll(".sidebar-close").forEach(function (btn) {
    btn.addEventListener("click", closeSidebar);
  });

  /* ---- Sidebar submenus (groups with data-nav-toggle) ---- */
  document.querySelectorAll("[data-nav-toggle]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var group = btn.closest(".nav-group");
      if (!group) return;
      var open = group.classList.toggle("open");
      btn.setAttribute("aria-expanded", open ? "true" : "false");
    });
  });

  /* ---- Picking a destination closes the off-canvas sidebar ---- */
  document.querySelectorAll("#sidebar a").forEach(function (link) {
    link.addEventListener("click", function () {
      if (window.matchMedia("(max-width: 992px)").matches) closeSidebar();
    });
  });

  /* ---- Modal dialogs (all CRUD forms open here) ---- */
  function openModal(modal) {
    if (!modal) return;
    modal.classList.add("open");
    document.body.classList.add("modal-open");
  }
  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove("open");
    if (!document.querySelector(".modal.open")) document.body.classList.remove("modal-open");
  }
  function snakeCase(key) {
    return key.replace(/[A-Z]/g, function (c) { return "_" + c.toLowerCase(); });
  }

  document.addEventListener("click", function (event) {
    var openTrigger = event.target.closest("[data-modal-open]");
    if (openTrigger) {
      var modal = document.getElementById(openTrigger.getAttribute("data-modal-open"));
      if (modal) {
        event.preventDefault();
        var data = openTrigger.dataset;
        Object.keys(data).forEach(function (key) {
          if (key === "modalOpen") return;
          // data-fill-xxx="text" -> <span data-fill="xxx">
          if (key.indexOf("fill") === 0 && key.length > 4) {
            var target = modal.querySelector('[data-fill="' + key.charAt(4).toLowerCase() + key.slice(5) + '"]');
            if (target) target.textContent = data[key];
            return;
          }
          // data-confirm-label / data-confirm-class -> [data-modal-confirm]
          if (key === "confirmLabel" || key === "confirmClass") {
            var confirmBtn = modal.querySelector("[data-modal-confirm]");
            if (confirmBtn) {
              if (key === "confirmLabel") confirmBtn.textContent = data[key];
              else confirmBtn.className = data[key];
            }
            return;
          }
          // data-kind="livestock" -> show only <optgroup data-kind="livestock">
          if (key === "kind") {
            modal.querySelectorAll("optgroup[data-kind]").forEach(function (group) {
              group.hidden = group.getAttribute("data-kind") !== data[key];
            });
            return;
          }
          // data-crop-id="12" -> [name="crop_id"], data-max-quantity-sold="5" -> max
          var name = snakeCase(key);
          var isMax = name.indexOf("max_") === 0;
          var field = modal.querySelector('[name="' + (isMax ? name.slice(4) : name) + '"]');
          if (!field) return;
          if (isMax) field.setAttribute("max", data[key]);
          else {
            field.value = data[key];
            if (field.tagName === "SELECT") field.dispatchEvent(new Event("change"));
          }
        });
        openModal(modal);
        var first = modal.querySelector("input:not([type=hidden]), select, textarea, button");
        if (first) first.focus();
      }
      return;
    }
    var closeTrigger = event.target.closest("[data-modal-close]");
    if (closeTrigger) {
      closeModal(closeTrigger.closest(".modal"));
      return;
    }
    if (event.target.classList && event.target.classList.contains("modal")) closeModal(event.target);
  });

  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") document.querySelectorAll(".modal.open").forEach(closeModal);
  });

  /* ---- Generic confirmation modal ----
     Add data-confirm="message" to any <a> or <form> that needs a second
     opinion (logout, close inquiry, mark-all-read…). Optional attributes:
     data-confirm-title, data-confirm-label (confirm button text),
     data-confirm-class (confirm button classes). The modal itself is built
     once, on first use, and reuses the open/close logic above. */
  var pendingConfirm = null;

  function ensureConfirmModal() {
    var existing = document.getElementById("confirm-modal");
    if (existing) return existing;
    var wrap = document.createElement("div");
    wrap.className = "modal modal-sm";
    wrap.id = "confirm-modal";
    wrap.setAttribute("role", "dialog");
    wrap.setAttribute("aria-modal", "true");
    wrap.innerHTML =
      '<div class="modal-card">' +
        '<div class="modal-head">' +
          '<h3 class="modal-title" data-confirm-title>Are you sure?</h3>' +
          '<button class="modal-close" type="button" data-modal-close aria-label="Close dialog">&#10005;</button>' +
        '</div>' +
        '<div class="modal-body">' +
          '<p class="modal-note" data-confirm-message></p>' +
          '<div class="modal-actions">' +
            '<button class="btn btn-ghost" type="button" data-modal-close data-confirm-cancel>Cancel</button>' +
            '<button class="btn btn-danger" type="button" data-confirm-yes>Confirm</button>' +
          '</div>' +
        '</div>' +
      '</div>';
    document.body.appendChild(wrap);
    wrap.addEventListener("click", function (event) {
      if (event.target.closest("[data-confirm-cancel]")) {
        pendingConfirm = null;
        return;
      }
      if (event.target.closest("[data-confirm-yes]")) {
        var job = pendingConfirm;
        pendingConfirm = null;
        closeModal(wrap);
        if (!job) return;
        if (job.type === "link") {
          window.location.href = job.href;
        } else if (job.form) {
          HTMLFormElement.prototype.submit.call(job.form);
        }
      }
    });
    return wrap;
  }

  function askConfirmation(trigger, event) {
    var message = trigger.getAttribute("data-confirm");
    if (!message) return;
    if (event) event.preventDefault();
    var modal = ensureConfirmModal();
    modal.querySelector("[data-confirm-title]").textContent =
      trigger.getAttribute("data-confirm-title") || "Are you sure?";
    modal.querySelector("[data-confirm-message]").textContent = message;
    var yes = modal.querySelector("[data-confirm-yes]");
    yes.textContent = trigger.getAttribute("data-confirm-label") || "Confirm";
    yes.className = trigger.getAttribute("data-confirm-class") || "btn btn-danger";
    pendingConfirm = trigger.tagName === "FORM"
      ? { type: "form", form: trigger }
      : { type: "link", href: trigger.getAttribute("href") || "" };
    openModal(modal);
    modal.querySelector("[data-confirm-cancel]").focus();
  }

  document.addEventListener("click", function (event) {
    var link = event.target.closest("a[data-confirm]");
    if (link) askConfirmation(link, event);
  });
  document.addEventListener("submit", function (event) {
    if (event.target && event.target.matches && event.target.matches("form[data-confirm]")) {
      askConfirmation(event.target, event);
    }
  });

  /* ---- Rows-per-page selector submits its form ---- */
  document.querySelectorAll("[data-autosubmit]").forEach(function (select) {
    select.addEventListener("change", function () {
      if (select.form) select.form.submit();
    });
  });

  /* ---- Dropdown menus (notifications / user chip) ---- */
  document.querySelectorAll("[data-dropdown]").forEach(function (trigger) {
    trigger.addEventListener("click", function (event) {
      event.stopPropagation();
      var menu = document.getElementById(trigger.getAttribute("data-dropdown"));
      document.querySelectorAll(".dropdown-menu.open").forEach(function (open) {
        if (open !== menu) open.classList.remove("open");
      });
      if (menu) menu.classList.toggle("open");
    });
  });
  document.addEventListener("click", function (event) {
    if (!event.target.closest(".dropdown")) {
      document.querySelectorAll(".dropdown-menu.open").forEach(function (menu) {
        menu.classList.remove("open");
      });
    }
  });
  document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
      document.querySelectorAll(".dropdown-menu.open").forEach(function (m) { m.classList.remove("open"); });
      closeSidebar();
    }
  });

  /* ---- Public landing nav (mobile) ---- */
  var navToggle = document.getElementById("navToggle");
  var navLinks = document.getElementById("navLinks");
  if (navToggle && navLinks) {
    navToggle.addEventListener("click", function () {
      navLinks.classList.toggle("open");
    });
  }

  /* ---- Geolocation: fill pickup coordinates from the device ---- */
  document.querySelectorAll("[data-geolocate]").forEach(function (button) {
    button.addEventListener("click", function () {
      var lat = document.getElementById(button.getAttribute("data-geolocate") + "_lat");
      var lng = document.getElementById(button.getAttribute("data-geolocate") + "_lng");
      if (!lat || !lng) return;
      if (!navigator.geolocation) {
        alert("Geolocation is not supported by this browser.");
        return;
      }
      button.disabled = true;
      button.textContent = "Locating…";
      navigator.geolocation.getCurrentPosition(
        function (pos) {
          lat.value = pos.coords.latitude.toFixed(8);
          lng.value = pos.coords.longitude.toFixed(8);
          button.disabled = false;
          button.textContent = "Use my location";
        },
        function () {
          alert("Location permission was denied or unavailable. You may type the coordinates manually.");
          button.disabled = false;
          button.textContent = "Use my location";
        },
        { enableHighAccuracy: true, timeout: 10000 }
      );
    });
  });

  /* ---- Show / hide password toggles (icon swaps via aria-pressed) ---- */
  document.querySelectorAll("[data-pw-toggle]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var input = document.getElementById(btn.getAttribute("data-pw-toggle"));
      if (!input) return;
      var show = input.type === "password";
      input.type = show ? "text" : "password";
      btn.setAttribute("aria-pressed", show ? "true" : "false");
      btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
      btn.setAttribute("title", show ? "Hide password" : "Show password");
      input.focus();
    });
  });

  /* ---- Names must never contain digits (live filter) ---- */
  document.querySelectorAll('input[name="first_name"], input[name="last_name"]').forEach(function (input) {
    input.addEventListener("input", function () {
      var cleaned = input.value.replace(/[0-9]/g, "");
      if (cleaned === input.value) return;
      var caret = input.selectionStart || 0;
      var removedBeforeCaret = input.value.slice(0, caret).match(/[0-9]/g);
      input.value = cleaned;
      var pos = caret - (removedBeforeCaret ? removedBeforeCaret.length : 0);
      try {
        input.setSelectionRange(pos, pos);
      } catch (e) { /* number/other input types ignore selection */ }
    });
  });

  /* ---- Auto-dismiss flash messages ---- */
  document.querySelectorAll(".alert[data-auto-hide]").forEach(function (el) {
    setTimeout(function () {
      el.style.transition = "opacity .5s";
      el.style.opacity = "0";
      setTimeout(function () { el.remove(); }, 500);
    }, 6000);
  });

  /* ---- Login success interstitial: forward once the ring/bar finish ----
     Accounts held on the pending-verification notice carry data-hold and
     are never auto-forwarded — the user chooses when to continue. */
  var lsBox = document.querySelector("[data-login-success]");
  if (lsBox && !lsBox.hasAttribute("data-hold")) {
    setTimeout(function () {
      window.location.replace(lsBox.getAttribute("data-redirect") || "/");
    }, 1900);
  }
})();
