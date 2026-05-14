/* ============================================================
 * Kasir Pintar - Unified Frontend Script
 * Single source of truth: assets/js/script.js
 * ============================================================ */

/* --- GLOBAL NAVIGATION LOGIC --- */
(function globalNavigationLogic() {
  function updateClock() {
    const clockEl = document.getElementById("clockTime");
    if (!clockEl) return;

    const now = new Date();
    const hours = String(now.getHours()).padStart(2, "0");
    const minutes = String(now.getMinutes()).padStart(2, "0");
    const seconds = String(now.getSeconds()).padStart(2, "0");
    clockEl.textContent = `${hours}:${minutes}:${seconds}`;
  }

  document.addEventListener("DOMContentLoaded", function () {
    updateClock();
    setInterval(updateClock, 1000);

    const menuLinks = document.querySelectorAll(".sidebar-menu a");
    if (!menuLinks.length) return;

    menuLinks.forEach((link) => {
      link.addEventListener("click", function () {
        menuLinks.forEach((item) => item.classList.remove("active"));
        this.classList.add("active");
      });
    });
  });
})();

/* --- GLOBAL SIDEBAR TOGGLE LOGIC --- */
(function globalSidebarToggleLogic() {
  function bindSidebarToggle() {
    const toggleButtons = document.querySelectorAll(
      '[data-sidebar-toggle="1"]',
    );
    const backdrop = document.getElementById("sidebarBackdrop");
    const sidebarLinks = document.querySelectorAll(".sidebar-menu a");
    if (!toggleButtons.length) return;

    const mobileQuery = window.matchMedia("(max-width: 48rem)");

    function isMobileViewport() {
      return mobileQuery.matches;
    }

    function syncSidebarState() {
      const isMobile = isMobileViewport();

      if (isMobile) {
        document.body.classList.remove("sidebar-collapsed");
      } else {
        document.body.classList.remove("sidebar-open");
      }

      const isCollapsedDesktop =
        document.body.classList.contains("sidebar-collapsed");
      const isOpenMobile = document.body.classList.contains("sidebar-open");

      const sidebarVisible = isMobile ? isOpenMobile : !isCollapsedDesktop;

      toggleButtons.forEach((toggleBtn) => {
        toggleBtn.setAttribute(
          "aria-expanded",
          sidebarVisible ? "true" : "false",
        );
        toggleBtn.classList.toggle(
          "is-active",
          isMobile ? isOpenMobile : isCollapsedDesktop,
        );
      });

      if (backdrop) {
        backdrop.setAttribute(
          "aria-hidden",
          isMobile && isOpenMobile ? "false" : "true",
        );
      }
    }

    function closeMobileSidebar() {
      document.body.classList.remove("sidebar-open");
      syncSidebarState();
    }

    toggleButtons.forEach((toggleBtn) => {
      toggleBtn.addEventListener("click", () => {
        if (isMobileViewport()) {
          document.body.classList.toggle("sidebar-open");
        } else {
          document.body.classList.toggle("sidebar-collapsed");
        }
        syncSidebarState();
      });
    });

    if (backdrop) {
      backdrop.addEventListener("click", closeMobileSidebar);
    }

    sidebarLinks.forEach((link) => {
      link.addEventListener("click", () => {
        if (isMobileViewport()) {
          closeMobileSidebar();
        }
      });
    });

    if (typeof mobileQuery.addEventListener === "function") {
      mobileQuery.addEventListener("change", syncSidebarState);
    } else if (typeof mobileQuery.addListener === "function") {
      mobileQuery.addListener(syncSidebarState);
    }

    window.addEventListener("resize", syncSidebarState);
    syncSidebarState();
  }

  document.addEventListener("DOMContentLoaded", bindSidebarToggle);
})();

/* --- GLOBAL PREMIUM NOTIFICATION SYSTEM --- */
(function globalNotificationSystem() {
  function ensureContainer() {
    let container = document.getElementById("appNotificationContainer");
    if (!container) {
      container = document.createElement("div");
      container.id = "appNotificationContainer";
      container.className = "app-notification-container";
      container.setAttribute("aria-live", "polite");
      container.setAttribute("aria-atomic", "true");
      document.body.appendChild(container);
    }
    return container;
  }

  function mapType(type) {
    const safeType = String(type || "info").toLowerCase();
    if (
      safeType === "danger" ||
      safeType === "error" ||
      safeType === "failed"
    ) {
      return "error";
    }
    if (safeType === "warn" || safeType === "warning") {
      return "warning";
    }
    if (safeType === "success") {
      return "success";
    }
    return "info";
  }

  function createIcon(type) {
    if (type === "success") return "✓";
    if (type === "error") return "✕";
    if (type === "warning") return "!";
    return "i";
  }

  function notify(title, message, type = "info", options = {}) {
    const normalizedType = mapType(type);
    const safeTitle = String(title || "Notification");
    const safeMessage = String(message || "");

    const autoDismissMs = Number(options.autoDismissMs || 3000);
    const fadeOutMs = Number(options.fadeOutMs || 300);

    const container = ensureContainer();

    if (options.replaceExisting === true) {
      const selector = options.group
        ? `.app-notification[data-notification-group="${String(options.group).replace(/"/g, "")}"]`
        : ".app-notification";
      container.querySelectorAll(selector).forEach((existingItem) => {
        existingItem.remove();
      });
    }

    const item = document.createElement("div");
    item.className = `app-notification app-notification-${normalizedType}`;
    item.setAttribute("role", "status");
    if (options.group) {
      item.dataset.notificationGroup = String(options.group);
    }
    item.innerHTML = `
      <div class="app-notification-icon" aria-hidden="true">${createIcon(normalizedType)}</div>
      <div class="app-notification-content">
        <div class="app-notification-title">${safeTitle}</div>
        <div class="app-notification-message">${safeMessage}</div>
      </div>
    `;

    container.appendChild(item);

    requestAnimationFrame(() => {
      item.classList.add("is-visible");
    });

    const startFadeOut = () => {
      if (!item.isConnected) return;
      item.classList.add("is-hiding");
      setTimeout(() => {
        if (item.isConnected) {
          item.remove();
        }
      }, fadeOutMs);
    };

    const hideTimer = setTimeout(startFadeOut, autoDismissMs);

    item.addEventListener("click", () => {
      clearTimeout(hideTimer);
      startFadeOut();
    });
  }

  window.appNotify = notify;
})();

/* --- GLOBAL AUTO HIDE STATIC ALERTS --- */
(function globalAutoHideAlerts() {
  function autoHideAlerts() {
    const alerts = document.querySelectorAll(
      ".auto-hide-alert, .alert.auto-hide, .alert[data-auto-hide='1']",
    );

    if (!alerts.length) return;

    alerts.forEach((alertEl) => {
      if (!(alertEl instanceof HTMLElement)) return;
      if (alertEl.dataset.autoHideBound === "1") return;

      alertEl.dataset.autoHideBound = "1";

      setTimeout(() => {
        if (!alertEl.isConnected) return;
        alertEl.classList.add("fade-out");

        setTimeout(() => {
          if (alertEl.isConnected) {
            alertEl.remove();
          }
        }, 500);
      }, 3000);
    });
  }

  document.addEventListener("DOMContentLoaded", autoHideAlerts);
})();

/* --- SETTINGS ATTENDANCE AJAX TOAST LOGIC --- */
(function settingsAttendanceToastLogic() {
  let clickedSubmitButton = null;
  let isSubmittingAttendance = false;

  function escapeHtml(value) {
    return String(value || "")
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function formatTimeFromDateTime(dateTimeText) {
    const raw = String(dateTimeText || "").trim();
    const timeMatch = raw.match(/(?:\s|T)(\d{2}:\d{2})(?::\d{2})?/);
    if (timeMatch) return timeMatch[1];

    const now = new Date();
    return `${String(now.getHours()).padStart(2, "0")}:${String(
      now.getMinutes(),
    ).padStart(2, "0")}`;
  }

  function showAttendanceNotification(title, message, type = "success") {
    if (typeof window.appNotify === "function") {
      window.appNotify(title, message, type, {
        autoDismissMs: 3000,
        fadeOutMs: 300,
        group: "settings-attendance",
        replaceExisting: true,
      });
      return;
    }

    if (type === "error" || type === "danger") {
      alert(`${title}: ${message}`);
    }
  }

  function setBusyState(isBusy) {
    const clockInBtn = document.getElementById("settingsClockInBtn");
    const clockOutBtn = document.getElementById("settingsClockOutBtn");

    [clockInBtn, clockOutBtn].forEach((btn) => {
      if (!btn) return;
      btn.dataset.busy = isBusy ? "1" : "0";
      btn.classList.toggle("disabled", Boolean(isBusy));
    });
  }

  function updateStatusUi(status) {
    const badge = document.getElementById("settingsAttendanceStatusBadge");
    const clockInBtn = document.getElementById("settingsClockInBtn");
    const clockOutBtn = document.getElementById("settingsClockOutBtn");
    const isOnline = status === "Online";

    if (badge) {
      badge.textContent = isOnline ? "Online" : "Offline";
      badge.classList.toggle("bg-success", isOnline);
      badge.classList.toggle("bg-secondary", !isOnline);
    }

    if (clockInBtn) clockInBtn.disabled = isOnline;
    if (clockOutBtn) clockOutBtn.disabled = !isOnline;
  }

  function removeEmptyHistoryRow(historyBody) {
    const emptyRow = historyBody
      .querySelector("td[colspan='2']")
      ?.closest("tr");
    if (emptyRow) emptyRow.remove();
  }

  function addClockInHistoryRow(clockInAt) {
    const historyBody = document.getElementById(
      "settingsAttendanceHistoryBody",
    );
    if (!historyBody) return;

    removeEmptyHistoryRow(historyBody);

    const row = document.createElement("tr");
    row.dataset.settingsAttendanceOpen = "1";
    row.innerHTML = `
      <td>${escapeHtml(clockInAt || "-")}</td>
      <td><span class="badge bg-warning text-dark">Belum Keluar</span></td>
    `;
    historyBody.prepend(row);
  }

  function updateClockOutHistoryRow(clockOutAt) {
    const historyBody = document.getElementById(
      "settingsAttendanceHistoryBody",
    );
    if (!historyBody) return;

    const openRow =
      historyBody.querySelector("tr[data-settings-attendance-open='1']") ||
      Array.from(historyBody.querySelectorAll("tr")).find((row) =>
        row.textContent.includes("Belum Keluar"),
      );

    if (!openRow) return;

    const cells = openRow.querySelectorAll("td");
    if (cells.length >= 2) {
      cells[1].textContent = clockOutAt || "-";
    }
    delete openRow.dataset.settingsAttendanceOpen;
  }

  async function submitAttendanceAction(form, action) {
    const formData = new FormData(form);
    formData.set("action", action);
    formData.set("ajax", "1");

    const response = await fetch(
      form.getAttribute("action") || "settings.php",
      {
        method: "POST",
        body: formData,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
        },
      },
    );

    let data = null;
    try {
      data = await response.json();
    } catch (error) {
      data = null;
    }

    if (!response.ok || !data || data.success !== true) {
      throw data || new Error("Attendance request failed");
    }

    return data;
  }

  function bindSettingsAttendanceForm() {
    const form = document.getElementById("settingsAttendanceForm");
    if (!form) return;

    form
      .querySelectorAll("button[type='submit'][name='action']")
      .forEach((btn) => {
        btn.addEventListener("click", () => {
          clickedSubmitButton = btn;
        });
      });

    form.addEventListener("submit", async (event) => {
      event.preventDefault();

      if (isSubmittingAttendance) return;

      const submitter = event.submitter || clickedSubmitButton;
      const action = submitter?.value || "";
      if (!action) return;

      isSubmittingAttendance = true;
      setBusyState(true);

      try {
        const data = await submitAttendanceAction(form, action);

        const toastTitle =
          data.action === "clock_out" ? "Absen Keluar" : "Absen Masuk";
        const toastMessage =
          data.action === "clock_out"
            ? "Tugas selesai, sampai jumpa!"
            : `Berhasil dicatat pada ${formatTimeFromDateTime(
                data.clock_in_at,
              )}`;

        showAttendanceNotification(toastTitle, toastMessage, "success");
        updateStatusUi(
          data.status || (data.action === "clock_out" ? "Offline" : "Online"),
        );

        if (data.action === "clock_in") {
          addClockInHistoryRow(data.clock_in_at);
        } else if (data.action === "clock_out") {
          updateClockOutHistoryRow(data.clock_out_at);
        }
      } catch (error) {
        showAttendanceNotification(
          "Gagal mencatat absen",
          "Silakan coba lagi.",
          "error",
        );
      } finally {
        setBusyState(false);
        isSubmittingAttendance = false;
        clickedSubmitButton = null;
      }
    });
  }

  document.addEventListener("DOMContentLoaded", bindSettingsAttendanceForm);
})();

/* --- SETTINGS PANEL TOGGLE LOGIC --- */
(function settingsPanelToggleLogic() {
  function bindSettingsPanelToggle() {
    const settingsRoot = document.querySelector(".settings-content");
    if (!settingsRoot) return;

    const toggleCards = settingsRoot.querySelectorAll(
      ".menu-card[data-settings-toggle]",
    );
    const panels = settingsRoot.querySelectorAll(
      ".settings-panel[data-settings-panel]",
    );
    if (!toggleCards.length || !panels.length) return;

    const storageKey = "kasirPintarActiveSettingsPanel";
    const panelMap = new Map();

    function setPanelHeight(panel, expanded) {
      if (!panel) return;
      panel.style.maxHeight = expanded ? `${panel.scrollHeight + 24}px` : "0px";
    }

    function syncCards(activePanelId) {
      toggleCards.forEach((card) => {
        card.classList.toggle(
          "is-active",
          card.dataset.settingsToggle === activePanelId,
        );
      });
    }

    function openPanel(panelId) {
      panels.forEach((panel) => {
        const isTarget = panel.dataset.settingsPanel === panelId;
        panel.classList.toggle("is-visible", isTarget);
        panel.setAttribute("aria-hidden", isTarget ? "false" : "true");
        setPanelHeight(panel, isTarget);
      });

      syncCards(panelId);

      if (panelId) {
        window.sessionStorage.setItem(storageKey, panelId);
      } else {
        window.sessionStorage.removeItem(storageKey);
      }
    }

    panels.forEach((panel) => {
      panelMap.set(panel.dataset.settingsPanel, panel);
      panel.classList.remove("is-visible");
      panel.setAttribute("aria-hidden", "true");
      panel.style.maxHeight = "0px";
    });

    toggleCards.forEach((card) => {
      card.addEventListener("click", (event) => {
        event.preventDefault();
        const targetId = card.dataset.settingsToggle || "";
        const targetPanel = panelMap.get(targetId);
        if (!targetPanel) return;

        const isAlreadyOpen = targetPanel.classList.contains("is-visible");
        openPanel(isAlreadyOpen ? "" : targetId);
      });
    });

    settingsRoot.querySelectorAll("form").forEach((form) => {
      form.addEventListener("submit", () => {
        const parentPanel = form.closest(
          ".settings-panel[data-settings-panel]",
        );
        if (!parentPanel) return;
        window.sessionStorage.setItem(
          storageKey,
          parentPanel.dataset.settingsPanel || "",
        );
      });
    });

    settingsRoot
      .querySelectorAll("[data-settings-focus-target]")
      .forEach((btn) => {
        btn.addEventListener("click", (event) => {
          event.preventDefault();
          const selector = btn.dataset.settingsFocusTarget || "";
          if (!selector) return;

          const target = settingsRoot.querySelector(selector);
          if (!target) return;

          target.scrollIntoView({ behavior: "smooth", block: "start" });

          const focusable = target.querySelector("input, select, textarea");
          if (focusable instanceof HTMLElement) {
            window.setTimeout(() => {
              focusable.focus();
            }, 220);
          }
        });
      });

    const initialPanelId =
      window.location.hash.replace(/^#/, "") ||
      window.sessionStorage.getItem(storageKey) ||
      "";

    if (initialPanelId && panelMap.has(initialPanelId)) {
      openPanel(initialPanelId);
    } else {
      openPanel("");
    }

    window.addEventListener("resize", () => {
      panels.forEach((panel) => {
        if (panel.classList.contains("is-visible")) {
          setPanelHeight(panel, true);
        }
      });
    });
  }

  document.addEventListener("DOMContentLoaded", bindSettingsPanelToggle);
})();

/* --- SETTINGS PROFILE PHOTO PREVIEW LOGIC --- */
(function settingsProfilePhotoLogic() {
  function bindSettingsProfilePhotoLogic() {
    const input = document.getElementById("profilePhotoInput");
    const preview = document.getElementById("settingsProfileAvatarPreview");
    const placeholder = document.getElementById(
      "settingsProfileAvatarPlaceholder",
    );

    if (!(input instanceof HTMLInputElement) || !preview || !placeholder)
      return;

    input.addEventListener("change", () => {
      const file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) return;

      const reader = new FileReader();
      reader.onload = (event) => {
        preview.src = String(event.target?.result || "");
        preview.classList.remove("hidden");
        placeholder.classList.add("hidden");
      };
      reader.readAsDataURL(file);
    });
  }

  document.addEventListener("DOMContentLoaded", bindSettingsProfilePhotoLogic);
})();

/* --- DASHBOARD LOGIC --- */
(function dashboardLogic() {
  const root = document.getElementById("dashboardPageRoot");
  if (!root) return;

  const role = root.dataset.role || "guest";
  const isLoggedIn = root.dataset.isLoggedIn === "1";

  const cashierWidget = document.getElementById("attendanceCashierWidget");
  const adminGrid = document.getElementById("attendanceAdminGrid");

  function padTwo(value) {
    return String(value).padStart(2, "0");
  }

  function formatDateTimeLocal(dateText) {
    if (!dateText) return "-";
    const normalized = String(dateText).replace(" ", "T");
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return "-";

    const dd = padTwo(date.getDate());
    const mm = padTwo(date.getMonth() + 1);
    const yy = date.getFullYear();
    const hh = padTwo(date.getHours());
    const mi = padTwo(date.getMinutes());
    const ss = padTwo(date.getSeconds());
    return `${dd}/${mm}/${yy} ${hh}:${mi}:${ss}`;
  }

  function formatClockOnly(dateText) {
    if (!dateText) return "-";
    const normalized = String(dateText).replace(" ", "T");
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return "-";
    return `${padTwo(date.getHours())}:${padTwo(date.getMinutes())}:${padTwo(date.getSeconds())}`;
  }

  function formatDuration(seconds) {
    const safeSeconds = Math.max(0, Number(seconds) || 0);
    const hh = Math.floor(safeSeconds / 3600);
    const mm = Math.floor((safeSeconds % 3600) / 60);
    const ss = Math.floor(safeSeconds % 60);
    return `${padTwo(hh)}:${padTwo(mm)}:${padTwo(ss)}`;
  }

  async function attendancePost(action) {
    const formData = new FormData();
    formData.append("attendance_action", action);

    const response = await fetch("dashboard.php", {
      method: "POST",
      body: formData,
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    return response.json();
  }

  function showAttendanceToast(title, message, type = "info") {
    if (typeof window.appNotify === "function") {
      window.appNotify(title, message, type, {
        autoDismissMs: 3000,
        fadeOutMs: 300,
      });
      return;
    }

    if (
      String(type).toLowerCase() === "danger" ||
      String(type).toLowerCase() === "error"
    ) {
      alert(`${title}: ${message}`);
    }
  }

  // Cashier attendance widget logic
  function initCashierAttendance() {
    if (!cashierWidget || role !== "kasir" || !isLoggedIn) return;

    const toggleBtn = document.getElementById("attendanceToggleBtn");
    const toggleLabel = document.getElementById("attendanceToggleLabel");
    const badge = document.getElementById("attendanceStatusBadge");
    const clockInEl = document.getElementById("attendanceClockInValue");
    const clockOutEl = document.getElementById("attendanceClockOutValue");
    const durationEl = document.getElementById("attendanceDurationValue");

    let state = {
      status: cashierWidget.dataset.status || "Pulang",
      clockIn: cashierWidget.dataset.clockIn || "",
      clockOut: cashierWidget.dataset.clockOut || "",
      totalHours: Number(cashierWidget.dataset.totalHours || 0) || 0,
      durationSeconds: 0,
    };

    function recomputeDurationSeconds() {
      if (state.status === "Masuk" && state.clockIn) {
        const startTime = new Date(
          String(state.clockIn).replace(" ", "T"),
        ).getTime();
        if (!Number.isNaN(startTime)) {
          state.durationSeconds = Math.max(
            0,
            Math.floor((Date.now() - startTime) / 1000),
          );
          return;
        }
      }

      state.durationSeconds = Math.round(
        (Number(state.totalHours) || 0) * 3600,
      );
    }

    function syncUi() {
      if (!badge || !toggleLabel || !durationEl || !clockInEl || !clockOutEl)
        return;

      const isOnline = state.status === "Masuk";
      badge.classList.toggle("online", isOnline);
      badge.classList.toggle("offline", !isOnline);
      badge.textContent = isOnline ? "On Duty" : "Off Duty";

      toggleLabel.textContent = isOnline ? "Absen Pulang" : "Absen Masuk";
      clockInEl.textContent = state.clockIn
        ? formatDateTimeLocal(state.clockIn)
        : "-";
      clockOutEl.textContent = state.clockOut
        ? formatDateTimeLocal(state.clockOut)
        : "-";
      durationEl.textContent = formatDuration(state.durationSeconds);
    }

    function hydrateAttendance(attendance) {
      state.status = attendance.status || "Pulang";
      state.clockIn = attendance.clock_in || "";
      state.clockOut = attendance.clock_out || "";
      state.totalHours = Number(attendance.total_hours || 0) || 0;
      recomputeDurationSeconds();
      syncUi();
    }

    async function refreshStatusSilently() {
      try {
        const data = await attendancePost("status");
        if (data && data.success && data.attendance) {
          hydrateAttendance(data.attendance);
        }
      } catch (error) {
        // silent refresh, ignore
      }
    }

    async function handleToggle() {
      if (!toggleBtn) return;

      toggleBtn.disabled = true;
      try {
        const data = await attendancePost("toggle");
        if (!data.success) {
          showAttendanceToast(
            "Attendance",
            data.message || "Gagal memproses attendance.",
            "danger",
          );
          return;
        }

        hydrateAttendance(data.attendance || {});
        showAttendanceToast(
          "Attendance",
          data.message || "Attendance berhasil diproses.",
          "success",
        );
      } catch (error) {
        showAttendanceToast(
          "Attendance",
          "Terjadi kesalahan saat memproses attendance.",
          "danger",
        );
      } finally {
        toggleBtn.disabled = false;
      }
    }

    if (toggleBtn) {
      toggleBtn.addEventListener("click", handleToggle);
    }

    recomputeDurationSeconds();
    syncUi();

    setInterval(() => {
      if (state.status === "Masuk") {
        state.durationSeconds += 1;
      }
      durationEl.textContent = formatDuration(state.durationSeconds);
    }, 1000);

    setInterval(refreshStatusSilently, 15000);
  }

  // Admin live monitor logic
  function initAdminMonitor() {
    if (!adminGrid || role !== "admin" || !isLoggedIn) return;

    function renderRows(cashiers) {
      if (!Array.isArray(cashiers) || cashiers.length === 0) {
        adminGrid.innerHTML =
          '<div class="attendance-empty">Belum ada data kasir untuk dimonitor.</div>';
        return;
      }

      adminGrid.innerHTML = cashiers
        .map((cashier) => {
          const isOnline = cashier.status === "Masuk";
          const safeName = cashier.name || "Kasir";
          const safeUsername = cashier.username || "cashier";
          const clockIn = formatClockOnly(cashier.clock_in);
          const clockOut = formatClockOnly(cashier.clock_out);
          const duration = formatDuration(cashier.duration_seconds || 0);

          return `
            <div class="attendance-admin-item ${isOnline ? "online" : "offline"}" data-status="${cashier.status || "Pulang"}" data-duration-seconds="${Number(cashier.duration_seconds || 0)}">
              <div class="attendance-admin-top">
                <div class="attendance-admin-user">
                  <div class="attendance-admin-name">${safeName}</div>
                  <div class="attendance-admin-username">@${safeUsername}</div>
                </div>
                <div class="attendance-live-indicator-wrap">
                  <span class="attendance-live-indicator"></span>
                </div>
              </div>
              <div class="attendance-admin-meta">
                <div><span>Clock In:</span> <strong>${clockIn}</strong></div>
                <div><span>Clock Out:</span> <strong>${clockOut}</strong></div>
                <div><span>Work Timer:</span> <strong class="attendance-admin-duration">${duration}</strong></div>
              </div>
            </div>
          `;
        })
        .join("");
    }

    async function refreshMonitor() {
      try {
        const data = await attendancePost("monitor");
        if (!data.success) return;
        renderRows(data.cashiers || []);
      } catch (error) {
        // keep UI stable on network hiccup
      }
    }

    setInterval(() => {
      adminGrid.querySelectorAll(".attendance-admin-item").forEach((itemEl) => {
        const isOnline = itemEl.dataset.status === "Masuk";
        if (!isOnline) return;

        const durationEl = itemEl.querySelector(".attendance-admin-duration");
        const current = Number(itemEl.dataset.durationSeconds || 0) || 0;
        const next = current + 1;
        itemEl.dataset.durationSeconds = String(next);
        if (durationEl) {
          durationEl.textContent = formatDuration(next);
        }
      });
    }, 1000);

    setInterval(refreshMonitor, 12000);
  }

  document.addEventListener("DOMContentLoaded", () => {
    initCashierAttendance();
    initAdminMonitor();
  });
})();

/* --- SIDEBAR GUEST ACCESS LOCK LOGIC --- */
(function sidebarGuestAccessLockLogic() {
  document.addEventListener("DOMContentLoaded", function () {
    const lockedLinks = document.querySelectorAll(
      '.sidebar-menu a[data-guest-lock="1"]',
    );
    const overlay = document.getElementById("guestAccessOverlay");
    if (!lockedLinks.length || !overlay) return;

    const showOverlay = () => {
      overlay.style.display = "flex";
      setTimeout(() => {
        overlay.style.display = "none";
      }, 1300);
    };

    lockedLinks.forEach((link) => {
      link.addEventListener("click", function (event) {
        event.preventDefault();
        link.setAttribute("title", "Anda harus login untuk membuka akses ini");
        showOverlay();
      });
    });
  });
})();

/* --- PRODUCT MANAGEMENT LOGIC --- */
(function productManagementLogic() {
  const root = document.getElementById("productsPageRoot");
  if (!root) return;

  const isGuestMode = root.dataset.isGuestMode === "1";

  function loadScript(src) {
    return new Promise((resolve, reject) => {
      const existing = document.querySelector(`script[src=\"${src}\"]`);
      if (existing) {
        if (existing.dataset.loaded === "1") {
          resolve(true);
          return;
        }

        existing.addEventListener("load", () => resolve(true), { once: true });
        existing.addEventListener(
          "error",
          () => reject(new Error(`Failed to load: ${src}`)),
          {
            once: true,
          },
        );
        return;
      }

      const script = document.createElement("script");
      script.src = src;
      script.async = true;
      script.addEventListener(
        "load",
        () => {
          script.dataset.loaded = "1";
          resolve(true);
        },
        { once: true },
      );
      script.addEventListener(
        "error",
        () => reject(new Error(`Failed to load: ${src}`)),
        {
          once: true,
        },
      );
      document.head.appendChild(script);
    });
  }

  function ensureDependencies() {
    const jobs = [];

    if (typeof bootstrap === "undefined") {
      jobs.push(
        loadScript(
          "https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js",
        ),
      );
    }

    if (typeof Swal === "undefined") {
      jobs.push(loadScript("https://cdn.jsdelivr.net/npm/sweetalert2@11"));
    }

    if (!jobs.length) {
      return Promise.resolve();
    }

    return Promise.allSettled(jobs).then(() => undefined);
  }

  function showGuestAccessMessage(event) {
    if (event && typeof event.preventDefault === "function") {
      event.preventDefault();
    }

    if (typeof Swal !== "undefined") {
      Swal.fire({
        icon: "info",
        title: "Akses Terkunci",
        text: "Anda harus login untuk membuka akses ini",
        confirmButtonText: "Oke",
      });
    } else {
      alert("Anda harus login untuk membuka akses ini");
    }

    return false;
  }

  window.showGuestAccessMessage = showGuestAccessMessage;

  const categoryPrefixes = {
    Makanan: "MK-",
    Minuman: "MN-",
    Snack: "SN-",
    "Es Krim": "EK-",
    "Bumbu Dapur": "BM-",
    Bakery: "BK-",
    "Peralatan & Perkakas": "PL-",
    "Kebutuhan Bayi": "BY-",
    "Pembersih & Sabun": "PB-",
    "Lain-lain": "LL-",
  };

  function getCategoryPrefix(categoryValue) {
    return categoryValue && categoryPrefixes[categoryValue]
      ? categoryPrefixes[categoryValue]
      : "";
  }

  function extractNumericSuffix(value, prefix = "") {
    const stringValue = String(value || "");
    const suffixSource =
      prefix && stringValue.startsWith(prefix)
        ? stringValue.slice(prefix.length)
        : stringValue;

    return suffixSource.replace(/\D/g, "");
  }

  function enforceProductCodePrefix(categorySelect, codeInput, options = {}) {
    const prefix = getCategoryPrefix(categorySelect.value);
    const clearWithoutPrefix = options.clearWithoutPrefix ?? false;
    const previousCaret = codeInput.selectionStart ?? codeInput.value.length;
    const valueBeforeCaret = codeInput.value.slice(0, previousCaret);

    if (!prefix) {
      codeInput.dataset.prefix = "";
      if (clearWithoutPrefix) {
        codeInput.value = "";
      }
      return;
    }

    const numbersBeforeCaret = extractNumericSuffix(
      valueBeforeCaret,
      prefix,
    ).length;
    const numericSuffix = extractNumericSuffix(codeInput.value, prefix);

    codeInput.dataset.prefix = prefix;
    codeInput.value = prefix + numericSuffix;

    const caretPosition = Math.max(
      prefix.length,
      Math.min(prefix.length + numbersBeforeCaret, codeInput.value.length),
    );

    requestAnimationFrame(() => {
      if (document.activeElement === codeInput) {
        codeInput.setSelectionRange(caretPosition, caretPosition);
      }
    });
  }

  function replaceSelectedSuffix(codeInput, replacement) {
    const prefix = codeInput.dataset.prefix || "";
    if (!prefix) return false;

    const replacementDigits = String(replacement || "").replace(/\D/g, "");
    const suffix = codeInput.value.slice(prefix.length).replace(/\D/g, "");
    const start = Math.max(
      0,
      (codeInput.selectionStart ?? prefix.length) - prefix.length,
    );
    const end = Math.max(
      0,
      (codeInput.selectionEnd ?? prefix.length) - prefix.length,
    );
    const newSuffix =
      suffix.slice(0, start) + replacementDigits + suffix.slice(end);
    const newCaret = prefix.length + start + replacementDigits.length;

    codeInput.value = prefix + newSuffix;
    codeInput.setSelectionRange(newCaret, newCaret);
    return true;
  }

  function keepCaretAfterPrefix(codeInput) {
    const prefix = codeInput.dataset.prefix || "";
    if (!prefix) return;

    const start = codeInput.selectionStart ?? 0;
    const end = codeInput.selectionEnd ?? 0;

    if (start < prefix.length || end < prefix.length) {
      codeInput.setSelectionRange(prefix.length, Math.max(prefix.length, end));
    }
  }

  function installProductCodeGuard(
    categoryId,
    codeId,
    clearWithoutPrefix = false,
  ) {
    const categorySelect = document.getElementById(categoryId);
    const codeInput = document.getElementById(codeId);
    if (!categorySelect || !codeInput) return;

    codeInput.addEventListener("keydown", function (event) {
      const prefix =
        codeInput.dataset.prefix || getCategoryPrefix(categorySelect.value);
      if (!prefix) return;

      const start = codeInput.selectionStart ?? 0;
      const end = codeInput.selectionEnd ?? 0;
      const key = event.key;
      const hasModifier = event.ctrlKey || event.metaKey || event.altKey;

      if (hasModifier) return;

      if (key === "Home") {
        event.preventDefault();
        codeInput.setSelectionRange(prefix.length, prefix.length);
        return;
      }

      if (
        key === "ArrowLeft" &&
        start <= prefix.length &&
        end <= prefix.length
      ) {
        event.preventDefault();
        codeInput.setSelectionRange(prefix.length, prefix.length);
        return;
      }

      if (key === "Backspace" || key === "Delete") {
        if (
          (key === "Backspace" && start <= prefix.length) ||
          start < prefix.length
        ) {
          event.preventDefault();
          if (end > prefix.length) {
            replaceSelectedSuffix(codeInput, "");
          } else {
            codeInput.setSelectionRange(prefix.length, prefix.length);
          }
        }
        return;
      }

      if (key.length === 1) {
        event.preventDefault();
        if (/\d/.test(key)) {
          replaceSelectedSuffix(codeInput, key);
        }
      }
    });

    codeInput.addEventListener("paste", function (event) {
      const prefix =
        codeInput.dataset.prefix || getCategoryPrefix(categorySelect.value);
      if (!prefix) return;
      event.preventDefault();
      replaceSelectedSuffix(codeInput, event.clipboardData.getData("text"));
    });

    codeInput.addEventListener("input", function () {
      enforceProductCodePrefix(categorySelect, codeInput, {
        clearWithoutPrefix,
      });
    });

    ["click", "keyup", "focus"].forEach((eventName) => {
      codeInput.addEventListener(eventName, function () {
        keepCaretAfterPrefix(codeInput);
      });
    });
  }

  function updateProductCode() {
    const categorySelect = document.getElementById("productCategory");
    const codeInput = document.getElementById("productCode");
    if (!categorySelect || !codeInput) return;
    enforceProductCodePrefix(categorySelect, codeInput, {
      clearWithoutPrefix: true,
    });
  }

  function updateEditProductCode() {
    const categorySelect = document.getElementById("editProductCategory");
    const codeInput = document.getElementById("editProductCode");
    if (!categorySelect || !codeInput) return;

    enforceProductCodePrefix(categorySelect, codeInput, {
      clearWithoutPrefix: false,
    });
  }

  function bindProductCategoryAutoCode() {
    const addCategory = document.getElementById("productCategory");
    const editCategory = document.getElementById("editProductCategory");

    if (addCategory) {
      addCategory.addEventListener("change", updateProductCode);
    }

    if (editCategory) {
      editCategory.addEventListener("change", updateEditProductCode);
    }
  }

  const FILTER_DEFAULTS = {
    search: "",
    category: "",
    stockStatus: "all",
    sortBy: "newest",
  };

  let filterDebounceTimer = null;
  let filterAbortController = null;

  function getFilterElements() {
    return {
      searchInput: document.getElementById("filterSearch"),
      categorySelect: document.getElementById("filterCategory"),
      stockSelect: document.getElementById("filterStockStatus"),
      sortSelect: document.getElementById("filterSortBy"),
      resetButton: document.getElementById("resetFilters"),
      tableSection: document.getElementById("productsTableSection"),
      summary: document.getElementById("filterSummary"),
      loading: document.getElementById("filterLoading"),
    };
  }

  function setFilterLoading(isLoading) {
    const { loading } = getFilterElements();
    if (!loading) return;
    loading.classList.toggle("show", Boolean(isLoading));
  }

  function updateFilterSummary(total) {
    const { summary } = getFilterElements();
    if (!summary) return;
    const count = Number.isFinite(Number(total)) ? Number(total) : 0;
    const label = count === 1 ? "product" : "products";
    summary.innerHTML = `<i class="bi bi-funnel me-1"></i> Showing ${count} ${label}`;
  }

  function buildFilterQueryParams() {
    const { searchInput, categorySelect, stockSelect, sortSelect } =
      getFilterElements();
    const params = new URLSearchParams();

    const searchValue = (searchInput?.value || "").trim();
    const categoryValue = categorySelect?.value || "";
    const stockValue = stockSelect?.value || FILTER_DEFAULTS.stockStatus;
    const sortValue = sortSelect?.value || FILTER_DEFAULTS.sortBy;

    if (searchValue) params.set("search", searchValue);
    if (categoryValue) params.set("category", categoryValue);
    if (stockValue !== FILTER_DEFAULTS.stockStatus) {
      params.set("stock_status", stockValue);
    }
    if (sortValue !== FILTER_DEFAULTS.sortBy) {
      params.set("sort_by", sortValue);
    }

    return params;
  }

  function syncFilterUrl() {
    const params = buildFilterQueryParams();
    const query = params.toString();
    const nextUrl = query ? `products.php?${query}` : "products.php";
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, document.title, nextUrl);
    }
  }

  function showToast(title, message, type) {
    if (typeof window.appNotify === "function") {
      window.appNotify(title, message, type, {
        autoDismissMs: 3000,
        fadeOutMs: 300,
      });
      return;
    }

    if (
      String(type).toLowerCase() === "danger" ||
      String(type).toLowerCase() === "error"
    ) {
      alert(`${title}: ${message}`);
    }
  }

  function fetchFilteredProducts() {
    const { tableSection } = getFilterElements();
    if (!tableSection) return;

    if (filterAbortController) filterAbortController.abort();
    filterAbortController = new AbortController();

    const params = buildFilterQueryParams();
    params.set("ajax", "1");

    setFilterLoading(true);

    fetch(`products.php?${params.toString()}`, {
      method: "GET",
      signal: filterAbortController.signal,
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    })
      .then((response) => response.json())
      .then((data) => {
        if (!data || data.success !== true) {
          throw new Error("Unable to load filtered products");
        }

        tableSection.innerHTML = data.html || "";
        updateFilterSummary(data.total || 0);
        syncFilterUrl();
      })
      .catch((error) => {
        if (error.name === "AbortError") return;
        console.error("Filter fetch error:", error);
        showToast("Error", "Failed to refresh filtered products", "danger");
      })
      .finally(() => {
        setFilterLoading(false);
      });
  }

  function queueSearchFilter() {
    clearTimeout(filterDebounceTimer);
    filterDebounceTimer = setTimeout(fetchFilteredProducts, 300);
  }

  function resetProductFilters() {
    const { searchInput, categorySelect, stockSelect, sortSelect } =
      getFilterElements();
    if (searchInput) searchInput.value = FILTER_DEFAULTS.search;
    if (categorySelect) categorySelect.value = FILTER_DEFAULTS.category;
    if (stockSelect) stockSelect.value = FILTER_DEFAULTS.stockStatus;
    if (sortSelect) sortSelect.value = FILTER_DEFAULTS.sortBy;
    fetchFilteredProducts();
  }

  function initializeProductFilters() {
    const {
      searchInput,
      categorySelect,
      stockSelect,
      sortSelect,
      resetButton,
    } = getFilterElements();

    if (
      !searchInput ||
      !categorySelect ||
      !stockSelect ||
      !sortSelect ||
      !resetButton
    ) {
      return;
    }

    searchInput.addEventListener("input", queueSearchFilter);
    categorySelect.addEventListener("change", fetchFilteredProducts);
    stockSelect.addEventListener("change", fetchFilteredProducts);
    sortSelect.addEventListener("change", fetchFilteredProducts);
    resetButton.addEventListener("click", resetProductFilters);
  }

  const KASIR_SWAL_CONFIG = Object.freeze({
    background: "#111827",
    color: "#e5e7eb",
    customClass: {
      popup: "swal2-dark-theme",
    },
    buttonsStyling: true,
    confirmButtonColor: "#10b981",
  });

  function fireKasirSwal(options = {}) {
    if (typeof Swal === "undefined") {
      const message =
        options.text ||
        options.title ||
        "Konfirmasi tidak tersedia di browser ini.";
      const isConfirmed = window.confirm(message);
      return Promise.resolve({ isConfirmed });
    }

    const mergedClass = {
      ...(KASIR_SWAL_CONFIG.customClass || {}),
      ...(options.customClass || {}),
    };

    return Swal.fire({
      ...KASIR_SWAL_CONFIG,
      ...options,
      customClass: mergedClass,
    });
  }

  function fireKasirToast(options = {}) {
    const icon = String(options.icon || "info");
    const title = String(options.title || "Notification");
    const text = String(options.text || "");
    const message = text || title;

    if (typeof window.appNotify === "function") {
      window.appNotify(title, message, icon, {
        autoDismissMs: 3000,
        fadeOutMs: 300,
      });
      return Promise.resolve({ isConfirmed: true });
    }

    if (icon === "error" || icon === "danger") {
      alert(`${title}: ${message}`);
    }

    return Promise.resolve({ isConfirmed: true });
  }

  function previewImage(input) {
    const preview = document.getElementById("imagePreview");
    if (!preview) return;

    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function (e) {
        preview.src = e.target.result;
        preview.classList.add("show");
      };
      reader.readAsDataURL(input.files[0]);
    } else {
      preview.classList.remove("show");
    }
  }

  function previewEditImage(input) {
    const preview = document.getElementById("editImagePreview");
    if (!preview) return;

    if (input.files && input.files[0]) {
      const reader = new FileReader();
      reader.onload = function (e) {
        preview.src = e.target.result;
        preview.classList.add("show");
      };
      reader.readAsDataURL(input.files[0]);
    } else {
      preview.classList.remove("show");
    }
  }

  function bindProductFormEvents() {
    const addImageInput = document.getElementById("productImage");
    const editImageInput = document.getElementById("editProductImage");
    const addSubmitBtn = document.getElementById("addSubmitBtn");
    const editSubmitBtn = document.getElementById("editSubmitBtn");

    if (addImageInput) {
      addImageInput.addEventListener("change", function () {
        previewImage(this);
      });
    }

    if (editImageInput) {
      editImageInput.addEventListener("change", function () {
        previewEditImage(this);
      });
    }

    if (addSubmitBtn) {
      addSubmitBtn.addEventListener("click", function () {
        submitAddProduct(this);
      });
    }

    if (editSubmitBtn) {
      editSubmitBtn.addEventListener("click", function () {
        submitEditProduct();
      });
    }
  }

  function submitAddProduct(button) {
    if (isGuestMode) return showGuestAccessMessage();

    const form = document.getElementById("addProductForm");
    if (!form) return;

    updateProductCode();
    const formData = new FormData(form);

    const submitBtn =
      button || document.querySelector("#addProductModal .btn-product-save");
    if (!submitBtn) return;

    submitBtn.disabled = true;
    submitBtn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

    fetch("process_product.php", {
      method: "POST",
      body: formData,
    })
      .then((response) => response.json())
      .then((data) => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Simpan Produk';

        if (data.success) {
          showToast("Success", data.message, "success");
          form.reset();
          const imagePreview = document.getElementById("imagePreview");
          if (imagePreview) imagePreview.classList.remove("show");

          setTimeout(() => {
            window.location.href =
              data.redirect ||
              `products.php?success=${encodeURIComponent(
                data.message || "Product added successfully",
              )}`;
          }, 1500);
        } else {
          showToast("Error", data.message, "danger");
        }
      })
      .catch((error) => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Simpan Produk';
        showToast("Error", "Failed to add product", "danger");
        console.error("Error:", error);
      });
  }

  function editProduct(productId) {
    if (isGuestMode) return showGuestAccessMessage();
    if (typeof bootstrap === "undefined") return;

    const modal = new bootstrap.Modal(
      document.getElementById("editProductModal"),
    );
    const form = document.getElementById("editProductForm");
    const loading = document.getElementById("editLoading");
    if (!form || !loading) return;

    form.classList.add("hidden");
    loading.classList.add("show");

    fetch(`process_product.php?action=get&id=${productId}`)
      .then((response) => response.json())
      .then((data) => {
        if (data.success) {
          const product = data.data;
          document.getElementById("editProductId").value = product.id;
          document.getElementById("editProductCode").value = product.code;
          document.getElementById("editProductName").value = product.name;
          document.getElementById("editProductCategory").value =
            product.category || "";
          updateEditProductCode();
          document.getElementById("editProductPrice").value = product.price;
          document.getElementById("editProductStock").value = product.stock;
          document.getElementById("editProductDescription").value =
            product.description || "";

          let currentImageHtml = "";
          if (product.image) {
            currentImageHtml = `<img src="../assets/img/${product.image}" class="edit-image-preview" alt="Current image">`;
          }
          document.getElementById("currentImage").innerHTML = currentImageHtml;

          loading.classList.remove("show");
          form.classList.remove("hidden");
          modal.show();
        } else {
          showToast("Error", "Failed to load product", "danger");
          loading.classList.remove("show");
          form.classList.remove("hidden");
        }
      })
      .catch((error) => {
        showToast("Error", "Failed to load product", "danger");
        loading.classList.remove("show");
        form.classList.remove("hidden");
        console.error("Error:", error);
      });
  }

  function submitEditProduct() {
    if (isGuestMode) return showGuestAccessMessage();

    fireKasirSwal({
      icon: "question",
      title: "Simpan Perubahan?",
      text: "Apakah Anda yakin ingin mengubah data produk ini?",
      showCancelButton: true,
      confirmButtonText: "Ya, Simpan!",
      cancelButtonText: "Batal",
      confirmButtonColor: "#10b981",
      reverseButtons: true,
    }).then((result) => {
      if (!result.isConfirmed) return;

      const form = document.getElementById("editProductForm");
      if (!form) return;

      updateEditProductCode();
      const formData = new FormData(form);

      const submitBtn = document.getElementById("editSubmitBtn");
      if (!submitBtn) return;

      submitBtn.disabled = true;
      submitBtn.innerHTML =
        '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';

      fetch("process_product.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Update Produk';

          if (data.success) {
            fireKasirToast({
              icon: "success",
              title: data.message || "Perubahan berhasil disimpan",
            });

            setTimeout(() => {
              window.location.href =
                data.redirect ||
                `products.php?success=${encodeURIComponent(
                  data.message || "Product updated successfully",
                )}`;
            }, 1200);
          } else {
            showToast("Error", data.message, "danger");
          }
        })
        .catch((error) => {
          submitBtn.disabled = false;
          submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Update Produk';
          showToast("Error", "Failed to update product", "danger");
          console.error("Error:", error);
        });
    });
  }

  function downloadLabel(productId) {
    if (isGuestMode) return showGuestAccessMessage();
    window.location.href = `generate_label.php?id=${productId}`;
  }

  function showDeleteSuccessToast(message) {
    fireKasirToast({
      icon: "success",
      title: message || "Produk berhasil dihapus",
    });
  }

  function deleteProduct(productId) {
    if (isGuestMode) return showGuestAccessMessage();

    fireKasirSwal({
      icon: "warning",
      title: "Hapus Produk?",
      text: "Data yang dihapus tidak bisa dikembalikan!",
      showCancelButton: true,
      confirmButtonText: "Ya, Hapus!",
      cancelButtonText: "Batal",
      confirmButtonColor: "#dc2626",
      reverseButtons: true,
    }).then((result) => {
      if (!result.isConfirmed) return;

      const formData = new FormData();
      formData.append("action", "delete");
      formData.append("id", productId);

      fetch("process_product.php", {
        method: "POST",
        body: formData,
      })
        .then((response) => response.json())
        .then((data) => {
          if (data.success) {
            showDeleteSuccessToast(data.message || "Produk berhasil dihapus");
            fetchFilteredProducts();
          } else {
            showToast(
              "Error",
              data.message || "Failed to delete product",
              "danger",
            );
          }
        })
        .catch((error) => {
          showToast("Error", "Failed to delete product", "danger");
          console.error("Error:", error);
        });
    });
  }

  function cleanProductUrl() {
    if (window.history && window.history.replaceState) {
      window.history.replaceState({}, document.title, "products.php");
    }
  }

  window.updateProductCode = updateProductCode;
  window.updateEditProductCode = updateEditProductCode;
  window.previewImage = previewImage;
  window.previewEditImage = previewEditImage;
  window.submitAddProduct = submitAddProduct;
  window.editProduct = editProduct;
  window.submitEditProduct = submitEditProduct;
  window.downloadLabel = downloadLabel;
  window.deleteProduct = deleteProduct;

  document.addEventListener("DOMContentLoaded", function () {
    ensureDependencies().finally(() => {
      installProductCodeGuard("productCategory", "productCode", true);
      installProductCodeGuard("editProductCategory", "editProductCode", false);
      bindProductCategoryAutoCode();
      bindProductFormEvents();
      initializeProductFilters();

      const sidebarLinks = document.querySelectorAll(".sidebar-menu a");
      sidebarLinks.forEach((link) => {
        link.classList.remove("active");
        const href = link.getAttribute("href") || "";
        if (
          href === "products.php" ||
          href.endsWith("/products/products.php")
        ) {
          link.classList.add("active");
        }
      });

      const params = new URLSearchParams(window.location.search);
      const successMessage = params.get("success");
      const errorMessage = params.get("error");

      if (successMessage) {
        showToast("Success", successMessage, "success");
        cleanProductUrl();
      } else if (errorMessage) {
        showToast("Error", errorMessage, "danger");
        cleanProductUrl();
      }
    });
  });
})();

/* --- LABEL GENERATION LOGIC --- */
(function labelGenerationLogic() {
  const root = document.getElementById("labelPageRoot");
  if (!root) return;

  const barcodeFilename = root.dataset.barcodeFilename || "";
  const productName = root.dataset.productName || "";
  const priceFormatted = root.dataset.priceFormatted || "";
  const productCode = root.dataset.productCode || "";

  function escapeHtml(value) {
    return String(value)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/\"/g, "&quot;")
      .replace(/'/g, "&#039;");
  }

  function createLabel() {
    const div = document.createElement("div");
    div.className = "label";

    const safeProductName = escapeHtml(productName);
    const safePriceFormatted = escapeHtml(priceFormatted);
    const safeProductCode = escapeHtml(productCode);

    div.innerHTML = `
      <div class="label-header">
        <div class="label-product-name">${safeProductName}</div>
      </div>
      <div class="label-barcode">
        ${
          barcodeFilename
            ? `<img src="/aplikasi-kasir-copy/assets/img/barcodes/${encodeURIComponent(
                barcodeFilename,
              )}" alt="Barcode ${safeProductCode}" class="label-barcode-image">`
            : '<div class="barcode-placeholder">Barcode</div>'
        }
      </div>
      <div class="label-footer">
        <div class="label-price">${safePriceFormatted}</div>
        <div class="label-code">${safeProductCode}</div>
      </div>
    `;

    return div;
  }

  function initializeLabels() {
    const quantityInput = document.getElementById("labelCount");
    const container = document.getElementById("labelContainer");
    if (!quantityInput || !container) return;

    const quantity = parseInt(quantityInput.value, 10) || 1;
    container.innerHTML = "";

    for (let i = 0; i < quantity; i++) {
      container.appendChild(createLabel());
    }
  }

  function generatePDF() {
    initializeLabels();
    setTimeout(() => {
      window.print();
    }, 100);
  }

  window.generatePDF = generatePDF;

  document.addEventListener("DOMContentLoaded", function () {
    const quantityInput = document.getElementById("labelCount");
    if (quantityInput) {
      quantityInput.addEventListener("change", initializeLabels);
    }
    initializeLabels();
  });
})();

/* --- TRANSACTION MODULE LOGIC --- */
(function transactionModuleLogic() {
  const root = document.getElementById("transactionPageRoot");
  if (!root) return;

  const codeInput = document.getElementById("transactionCodeInput");
  const addBtn = document.getElementById("transactionAddBtn");
  const cameraBtn = document.getElementById("transactionCameraScanBtn");
  const stopScanBtn = document.getElementById("transactionStopScanBtn");
  const clearCartBtn = document.getElementById("transactionClearCartBtn");
  const payBtn = document.getElementById("transactionPayBtn");
  const notesInput = document.getElementById("transactionNotes");
  const paymentMethodInput = document.getElementById(
    "transactionPaymentMethod",
  );
  const paymentAmountInput = document.getElementById(
    "transactionPaymentAmount",
  );
  const changeAmountEl = document.getElementById("transactionChangeAmount");
  const cartBody = document.getElementById("transactionCartBody");
  const subtotalEl = document.getElementById("transactionSubtotal");
  const taxEl = document.getElementById("transactionTax");
  const totalEl = document.getElementById("transactionTotal");
  const realtimeClockEl = document.getElementById("transactionRealtimeClock");

  let scannerInstance = null;
  let scannerIsRunning = false;
  let currentTotalAmount = 0;

  function formatRupiah(value) {
    const number = Number(value || 0);
    return `Rp ${new Intl.NumberFormat("id-ID").format(number)}`;
  }

  function updateTransactionClock() {
    if (!realtimeClockEl) return;
    const now = new Date();
    const hh = String(now.getHours()).padStart(2, "0");
    const mm = String(now.getMinutes()).padStart(2, "0");
    const ss = String(now.getSeconds()).padStart(2, "0");
    const dd = String(now.getDate()).padStart(2, "0");
    const mo = String(now.getMonth() + 1).padStart(2, "0");
    const yy = now.getFullYear();
    realtimeClockEl.textContent = `${dd}/${mo}/${yy} ${hh}:${mm}:${ss}`;
  }

  function showToast(title, message, type) {
    if (typeof window.appNotify === "function") {
      window.appNotify(title, message, type, {
        autoDismissMs: 3000,
        fadeOutMs: 300,
      });
      return;
    }

    if (
      String(type).toLowerCase() === "danger" ||
      String(type).toLowerCase() === "error"
    ) {
      alert(`${title}: ${message}`);
    }
  }

  function showTransactionSuccessAlert(title, text) {
    if (typeof window.appNotify === "function") {
      window.appNotify(title, text, "success", {
        autoDismissMs: 3000,
        fadeOutMs: 300,
      });
      return;
    }

    alert(`${title}: ${text}`);
  }

  function parseRupiahToNumber(rawText) {
    const normalized = String(rawText || "")
      .replace(/[^\d,.-]/g, "")
      .replace(/\./g, "")
      .replace(",", ".");
    const parsed = Number(normalized);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function getCurrentTotalAmount() {
    if (!totalEl) return Number(currentTotalAmount || 0);

    const parsedFromText = parseRupiahToNumber(totalEl.textContent);
    currentTotalAmount = Number.isFinite(parsedFromText)
      ? parsedFromText
      : Number(currentTotalAmount || 0);

    return Number(currentTotalAmount || 0);
  }

  function renderChangeAmount() {
    if (!changeAmountEl) return;
    const activeTotal = getCurrentTotalAmount();
    const paid = Number(paymentAmountInput ? paymentAmountInput.value : 0) || 0;
    const change = paid - activeTotal;
    const finalChange = change > 0 ? change : 0;
    changeAmountEl.textContent = formatRupiah(finalChange);
    changeAmountEl.classList.toggle("insufficient", paid < activeTotal);
  }

  function renderCartRows(items) {
    if (!cartBody) return;

    if (!items || !items.length) {
      cartBody.innerHTML = `
        <tr class="transaction-empty-row">
          <td colspan="6" class="text-center text-muted">Keranjang masih kosong.</td>
        </tr>
      `;
      return;
    }

    cartBody.innerHTML = items
      .map(
        (item) => `
          <tr>
            <td>${item.code}</td>
            <td>${item.name}</td>
            <td class="text-end">${formatRupiah(item.price)}</td>
            <td class="text-center">${item.qty}</td>
            <td class="text-end">${formatRupiah(item.line_subtotal)}</td>
            <td class="text-center">
              <button type="button" class="btn btn-sm btn-outline-danger transaction-remove-item-btn" data-product-id="${item.product_id}">
                <i class="fas fa-times"></i>
              </button>
            </td>
          </tr>
        `,
      )
      .join("");
  }

  function renderSummary(cart) {
    if (!cart) return;
    currentTotalAmount = Number(cart.total || 0);
    if (subtotalEl) subtotalEl.textContent = formatRupiah(cart.subtotal);
    if (taxEl) taxEl.textContent = formatRupiah(cart.tax);
    if (totalEl) totalEl.textContent = formatRupiah(cart.total);
    renderCartRows(cart.items || []);
    renderChangeAmount();
  }

  async function postAction(action, payload = {}) {
    const formData = new FormData();
    formData.append("action", action);

    Object.keys(payload).forEach((key) => {
      formData.append(key, payload[key]);
    });

    const response = await fetch("index.php", {
      method: "POST",
      body: formData,
      headers: {
        "X-Requested-With": "XMLHttpRequest",
      },
    });

    return response.json();
  }

  async function addCodeToCart(code) {
    const normalized = String(code || "").trim();
    if (!normalized) return;

    try {
      const data = await postAction("add_by_code", { code: normalized });
      if (!data.success) {
        showToast("Gagal", data.message || "Produk tidak ditemukan", "danger");
        return;
      }

      renderSummary(data.cart);
      showToast("Berhasil", data.message || "Produk ditambahkan", "success");
      if (codeInput) {
        codeInput.value = "";
        codeInput.focus();
      }
    } catch (error) {
      console.error(error);
      showToast("Error", "Gagal menambah item ke keranjang", "danger");
    }
  }

  async function removeItem(productId) {
    try {
      const data = await postAction("remove_item", { product_id: productId });
      if (!data.success) {
        showToast(
          "Gagal",
          data.message || "Tidak bisa menghapus item",
          "danger",
        );
        return;
      }
      renderSummary(data.cart);
    } catch (error) {
      console.error(error);
      showToast("Error", "Gagal menghapus item", "danger");
    }
  }

  async function clearCart() {
    try {
      const data = await postAction("clear_cart");
      if (!data.success) {
        showToast(
          "Gagal",
          data.message || "Tidak bisa kosongkan keranjang",
          "danger",
        );
        return;
      }
      renderSummary(data.cart);
      showToast("Info", data.message || "Keranjang dikosongkan", "info");
    } catch (error) {
      console.error(error);
      showToast("Error", "Gagal kosongkan keranjang", "danger");
    }
  }

  async function checkout() {
    if (!payBtn) return;

    const oldText = payBtn.innerHTML;
    payBtn.disabled = true;
    payBtn.innerHTML =
      '<span class="spinner-border spinner-border-sm me-2"></span>Memproses...';

    try {
      const data = await postAction("checkout", {
        notes: notesInput ? notesInput.value : "",
        payment_method: paymentMethodInput ? paymentMethodInput.value : "Cash",
        payment_amount: paymentAmountInput ? paymentAmountInput.value : "0",
      });

      if (!data.success) {
        showToast(
          "Checkout Gagal",
          data.message || "Gagal menyimpan transaksi",
          "danger",
        );
        return;
      }

      renderSummary(data.cart);
      if (notesInput) notesInput.value = "";
      if (paymentAmountInput) paymentAmountInput.value = "";
      renderChangeAmount();
      showTransactionSuccessAlert(
        "Transaksi Berhasil",
        `No. ${data.transaction_number || "-"} | Total: ${formatRupiah(data.total || 0)} | Bayar: ${formatRupiah(data.payment_amount || 0)} | Kembalian: ${formatRupiah(data.change_amount || 0)}`,
      );
    } catch (error) {
      console.error(error);
      showToast("Error", "Terjadi kesalahan saat checkout", "danger");
    } finally {
      payBtn.disabled = false;
      payBtn.innerHTML = oldText;
    }
  }

  function loadHtml5QrcodeScript() {
    return new Promise((resolve, reject) => {
      if (window.Html5Qrcode) {
        resolve(true);
        return;
      }

      const existing = document.querySelector(
        'script[src="https://unpkg.com/html5-qrcode"]',
      );
      if (existing) {
        existing.addEventListener("load", () => resolve(true), { once: true });
        existing.addEventListener(
          "error",
          () => reject(new Error("Gagal load Html5Qrcode")),
          { once: true },
        );
        return;
      }

      const script = document.createElement("script");
      script.src = "https://unpkg.com/html5-qrcode";
      script.async = true;
      script.onload = () => resolve(true);
      script.onerror = () => reject(new Error("Gagal load Html5Qrcode"));
      document.head.appendChild(script);
    });
  }

  async function startCameraScanner() {
    if (scannerIsRunning) return;

    try {
      await loadHtml5QrcodeScript();
      if (!window.Html5Qrcode) {
        showToast("Error", "Library scanner tidak tersedia", "danger");
        return;
      }

      scannerInstance = new window.Html5Qrcode("transactionScannerArea");
      await scannerInstance.start(
        { facingMode: "environment" },
        {
          fps: 10,
          qrbox: { width: 260, height: 120 },
          aspectRatio: 1.777,
        },
        (decodedText) => {
          addCodeToCart(decodedText);
        },
      );

      scannerIsRunning = true;
      if (cameraBtn) cameraBtn.disabled = true;
      if (stopScanBtn) stopScanBtn.disabled = false;
      showToast("Scanner Aktif", "Arahkan kamera ke barcode/QR", "info");
    } catch (error) {
      console.error(error);
      showToast("Error", "Tidak dapat membuka kamera scanner", "danger");
      scannerIsRunning = false;
    }
  }

  async function stopCameraScanner() {
    if (!scannerInstance || !scannerIsRunning) return;

    try {
      await scannerInstance.stop();
      await scannerInstance.clear();
    } catch (error) {
      console.error(error);
    } finally {
      scannerInstance = null;
      scannerIsRunning = false;
      if (cameraBtn) cameraBtn.disabled = false;
      if (stopScanBtn) stopScanBtn.disabled = true;
    }
  }

  function bindEvents() {
    if (addBtn) {
      addBtn.addEventListener("click", () => {
        addCodeToCart(codeInput ? codeInput.value : "");
      });
    }

    if (codeInput) {
      codeInput.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
          event.preventDefault();
          addCodeToCart(codeInput.value);
        }
      });
    }

    if (cameraBtn) {
      cameraBtn.addEventListener("click", startCameraScanner);
    }

    if (stopScanBtn) {
      stopScanBtn.addEventListener("click", stopCameraScanner);
    }

    if (clearCartBtn) {
      clearCartBtn.addEventListener("click", clearCart);
    }

    if (payBtn) {
      payBtn.addEventListener("click", checkout);
    }

    if (paymentAmountInput) {
      paymentAmountInput.addEventListener("input", renderChangeAmount);
      paymentAmountInput.addEventListener("keyup", renderChangeAmount);
      paymentAmountInput.addEventListener("change", renderChangeAmount);
    }

    if (paymentMethodInput) {
      paymentMethodInput.addEventListener("change", () => {
        if (paymentMethodInput.value !== "Cash") {
          paymentMethodInput.value = "Cash";
        }
      });
    }

    if (cartBody) {
      cartBody.addEventListener("click", (event) => {
        const btn = event.target.closest(".transaction-remove-item-btn");
        if (!btn) return;
        const productId = Number(btn.dataset.productId || 0);
        if (productId > 0) {
          removeItem(productId);
        }
      });
    }

    window.addEventListener("beforeunload", () => {
      stopCameraScanner();
    });
  }

  document.addEventListener("DOMContentLoaded", () => {
    bindEvents();
    updateTransactionClock();
    setInterval(updateTransactionClock, 1000);
    getCurrentTotalAmount();
    renderChangeAmount();

    const sidebarLinks = document.querySelectorAll(".sidebar-menu a");
    sidebarLinks.forEach((link) => {
      link.classList.remove("active");
      const href = link.getAttribute("href") || "";
      if (href.endsWith("/transactions/index.php")) {
        link.classList.add("active");
      }
    });
  });
})();

/* --- REPORTS MODULE LOGIC --- */
(function reportsModuleLogic() {
  const root = document.getElementById("reportsPageRoot");
  if (!root) return;

  const modalEl = document.getElementById("reportTransactionDetailModal");
  const trxNumberEl = document.getElementById("reportDetailTrxNumber");
  const cashierEl = document.getElementById("reportDetailCashier");
  const totalEl = document.getElementById("reportDetailTotal");
  const itemsBody = document.getElementById("reportDetailItemsBody");

  function formatRupiah(value) {
    const number = Number(value || 0);
    return `Rp ${new Intl.NumberFormat("id-ID").format(number)}`;
  }

  function renderItems(items) {
    if (!itemsBody) return;

    if (!Array.isArray(items) || items.length === 0) {
      itemsBody.innerHTML =
        '<tr><td colspan="4" class="text-center text-muted">Tidak ada item pada transaksi ini.</td></tr>';
      return;
    }

    itemsBody.innerHTML = items
      .map(
        (item) => `
          <tr>
            <td>${item.product_code || "-"}</td>
            <td>${item.product_name || "-"}</td>
            <td class="text-center">${Number(item.qty || 0)}</td>
            <td class="text-end">${formatRupiah(item.subtotal || 0)}</td>
          </tr>
        `,
      )
      .join("");
  }

  function openDetailModal(triggerBtn) {
    if (!modalEl || typeof bootstrap === "undefined") return;

    const trxNumber = triggerBtn.dataset.trxNumber || "-";
    const trxCashier = triggerBtn.dataset.trxCashier || "-";
    const trxTotal = triggerBtn.dataset.trxTotal || "-";
    let items = [];

    try {
      items = JSON.parse(triggerBtn.dataset.trxItems || "[]");
    } catch (error) {
      items = [];
    }

    if (trxNumberEl) trxNumberEl.textContent = trxNumber;
    if (cashierEl) cashierEl.textContent = trxCashier;
    if (totalEl) totalEl.textContent = trxTotal;
    renderItems(items);

    const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    modal.show();
  }

  document.addEventListener("click", (event) => {
    const triggerBtn = event.target.closest(".report-transaction-id-btn");
    if (!triggerBtn) return;
    event.preventDefault();
    openDetailModal(triggerBtn);
  });
})();
