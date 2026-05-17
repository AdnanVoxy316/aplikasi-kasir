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
    const headerRight = document.querySelector(
      ".settings-attendance-header-right",
    );
    let durationContainer = document.getElementById("settingsLiveDuration");

    if (badge) {
      badge.classList.toggle("online", isOnline);
      badge.classList.toggle("offline", !isOnline);
      badge.innerHTML = `
        <span class="settings-attendance-status-dot"></span>
        ${isOnline ? "On Duty" : "Off Duty"}
      `;
    }

    if (isOnline) {
      if (!durationContainer && headerRight) {
        durationContainer = document.createElement("div");
        durationContainer.id = "settingsLiveDuration";
        durationContainer.className = "settings-attendance-live-duration";
        durationContainer.innerHTML = `
          <i class="fas fa-hourglass-half"></i>
          <span class="settings-attendance-live-duration-label">Durasi:</span>
          <span class="settings-attendance-live-duration-value" id="settingsLiveDurationValue">00:00:00</span>
        `;
        headerRight.appendChild(durationContainer);
      }
    } else if (durationContainer) {
      durationContainer.remove();
    }

    if (clockInBtn) clockInBtn.disabled = isOnline;
    if (clockOutBtn) clockOutBtn.disabled = !isOnline;
  }

  function removeEmptyHistoryRow(historyBody) {
    const emptyRow = historyBody.querySelector("td.cell-empty")?.closest("tr");
    if (emptyRow) emptyRow.remove();
  }

  function ensureDateGroupRow(historyBody, rowData) {
    if (!historyBody) return;

    const dateKey = String(rowData?.date_key || "").trim();
    const groupLabel = String(rowData?.group_label || "").trim();
    if (!dateKey || !groupLabel) return;

    const existingGroupRow = historyBody.querySelector(
      `tr.date-merge-row[data-date-key="${CSS.escape(dateKey)}"]`,
    );
    if (existingGroupRow) return;

    const groupRow = document.createElement("tr");
    groupRow.className = "date-merge-row";
    groupRow.dataset.dateKey = dateKey;
    groupRow.innerHTML = `<td colspan="5">${escapeHtml(groupLabel)}</td>`;

    historyBody.prepend(groupRow);
  }

  function normalizeHistoryRow(
    rowData,
    fallbackClockInAt = "",
    fallbackClockOutAt = "",
  ) {
    const clockInAt = String(
      rowData?.clock_in_at || fallbackClockInAt || "",
    ).trim();
    const clockOutAt = String(
      rowData?.clock_out_at || fallbackClockOutAt || "",
    ).trim();
    const isOpen =
      rowData?.is_open === true ||
      rowData?.is_open === 1 ||
      rowData?.is_open === "1" ||
      (!clockOutAt &&
        String(rowData?.status_label || "").toLowerCase() === "on duty");

    return {
      dateLabel: String(rowData?.date_label || "-").trim() || "-",
      clockInTime:
        String(
          rowData?.clock_in_time || formatTimeFromDateTime(clockInAt),
        ).trim() || "-",
      clockOutTime:
        String(
          rowData?.clock_out_time ||
            (clockOutAt ? formatTimeFromDateTime(clockOutAt) : "--:--:--"),
        ).trim() || "--:--:--",
      durationHms:
        String(rowData?.duration_hms || "00:00:00").trim() || "00:00:00",
      statusLabel: String(
        rowData?.status_label || (isOpen ? "On Duty" : "Complete"),
      ).trim(),
      isOpen,
      clockInAt,
    };
  }

  function buildHistoryRowElement(
    rowData,
    fallbackClockInAt = "",
    fallbackClockOutAt = "",
  ) {
    const normalized = normalizeHistoryRow(
      rowData,
      fallbackClockInAt,
      fallbackClockOutAt,
    );
    const row = document.createElement("tr");
    if (rowData?.date_key) {
      row.dataset.dateKey = String(rowData.date_key);
    }
    if (normalized.isOpen) {
      row.dataset.settingsAttendanceOpen = "1";
    }
    if (normalized.clockInAt) {
      row.dataset.clockIn = normalized.clockInAt;
    }

    row.innerHTML = `
      <td class="cell-date">${escapeHtml(normalized.dateLabel)}</td>
      <td class="cell-time">${escapeHtml(normalized.clockInTime)}</td>
      <td class="cell-time">${escapeHtml(normalized.clockOutTime)}</td>
      <td class="cell-dur">${escapeHtml(normalized.durationHms)}</td>
      <td>
        <span class="badge-shift ${normalized.isOpen ? "on-duty" : "off-duty"}">
          ${escapeHtml(normalized.statusLabel)}
        </span>
      </td>
    `;

    return row;
  }

  function addClockInHistoryRow(rowData, clockInAt) {
    const historyBody = document.getElementById(
      "settingsAttendanceHistoryBody",
    );
    if (!historyBody) return;

    removeEmptyHistoryRow(historyBody);
    ensureDateGroupRow(historyBody, rowData || {});

    const row = buildHistoryRowElement(rowData, clockInAt || "", "");

    const dateKey = String(
      rowData?.date_key || row.dataset.dateKey || "",
    ).trim();
    const groupRow = dateKey
      ? historyBody.querySelector(
          `tr.date-merge-row[data-date-key="${CSS.escape(dateKey)}"]`,
        )
      : null;

    if (groupRow) {
      groupRow.insertAdjacentElement("afterend", row);
    } else {
      historyBody.prepend(row);
    }
  }

  function updateClockOutHistoryRow(rowData, clockOutAt) {
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

    const replacementRow = buildHistoryRowElement(
      rowData,
      openRow.dataset.clockIn || "",
      clockOutAt || "",
    );
    openRow.replaceWith(replacementRow);
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
          addClockInHistoryRow(data.history_row || {}, data.clock_in_at);
        } else if (data.action === "clock_out") {
          updateClockOutHistoryRow(data.history_row || {}, data.clock_out_at);
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

/* --- SETTINGS ATTENDANCE LIVE DURATION TIMER --- */
(function settingsAttendanceLiveDurationLogic() {
  let timerInterval = null;

  function formatDuration(seconds) {
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return `${String(h).padStart(2, "0")}:${String(m).padStart(2, "0")}:${String(s).padStart(2, "0")}`;
  }

  function getClockInFromRow() {
    const openRow = document.querySelector(
      "#settingsAttendanceHistoryBody tr[data-settings-attendance-open='1']",
    );
    if (!openRow) return null;
    const clockInRaw = openRow.dataset.clockIn || "";
    if (!clockInRaw) return null;
    const date = new Date(clockInRaw.replace(" ", "T"));
    return isNaN(date.getTime()) ? null : date;
  }

  function tick() {
    const clockInDate = getClockInFromRow();
    if (!clockInDate) {
      stopTimer();
      return;
    }
    const elapsed = Math.floor((Date.now() - clockInDate.getTime()) / 1000);
    const durationEl = document.getElementById("settingsLiveDurationValue");
    if (durationEl) {
      durationEl.textContent = formatDuration(elapsed);
    }
  }

  function startTimer() {
    if (timerInterval) return;
    tick();
    timerInterval = setInterval(tick, 1000);
  }

  function stopTimer() {
    if (timerInterval) {
      clearInterval(timerInterval);
      timerInterval = null;
    }
  }

  function checkAndStartTimer() {
    const durationEl = document.getElementById("settingsLiveDurationValue");
    const durationContainer = document.getElementById("settingsLiveDuration");
    const clockInDate = getClockInFromRow();
    if (clockInDate && durationEl) {
      if (durationContainer) durationContainer.style.display = "";
      startTimer();
    } else {
      if (durationEl) durationEl.textContent = "00:00:00";
      stopTimer();
    }
  }

  function observeAttendanceTable() {
    const tbody = document.getElementById("settingsAttendanceHistoryBody");
    if (!tbody) return;

    if (typeof MutationObserver !== "undefined") {
      const observer = new MutationObserver(() => {
        checkAndStartTimer();
      });
      observer.observe(tbody, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ["data-settings-attendance-open"],
      });
      checkAndStartTimer();
    } else {
      document.addEventListener("DOMContentLoaded", checkAndStartTimer);
      if (document.readyState !== "loading") checkAndStartTimer();
    }
  }

  document.addEventListener("DOMContentLoaded", observeAttendanceTable);
})();

/* --- SETTINGS PANEL TOGGLE LOGIC --- */
(function settingsPanelToggleLogic() {
  function bindSettingsPanelToggle() {
    const settingsRoot = document.querySelector(".settings-content");
    if (!settingsRoot) return;

    const myProfileCard = document.querySelector("#settingsMyProfileCard");
    const attendanceCard = document.querySelector("#settingsAttendanceCard");
    const toggleCards = settingsRoot.querySelectorAll(
      ".menu-card[data-settings-toggle]",
    );
    const panels = settingsRoot.querySelectorAll(
      ".settings-panel[data-settings-panel]",
    );
    if (!toggleCards.length || !panels.length) return;

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
    }

    function clearHash() {
      history.pushState(
        null,
        "",
        `${window.location.pathname}${window.location.search}`,
      );
    }

    function normalizeHash() {
      return String(window.location.hash || "")
        .replace(/^#/, "")
        .trim();
    }

    function handleNavigation() {
      const activeHash = normalizeHash();

      if (activeHash === "") {
        openPanel("");
        return;
      }

      if (panelMap.has(activeHash)) {
        openPanel(activeHash);
        return;
      }

      openPanel("");
    }

    panels.forEach((panel) => {
      panelMap.set(panel.dataset.settingsPanel, panel);
      panel.classList.remove("is-visible");
      panel.setAttribute("aria-hidden", "true");
      panel.style.maxHeight = "0px";
    });

    function isPanelOpen(targetId) {
      const panel = panelMap.get(targetId);
      return panel && panel.classList.contains("is-visible");
    }

    function closePanelIfOpen(targetId) {
      if (isPanelOpen(targetId)) {
        openPanel("");
        return true;
      }
      return false;
    }

    function bindToggleCard(card, explicitTargetId) {
      if (!card) return;
      card.addEventListener("click", (event) => {
        const targetId = explicitTargetId || card.dataset.settingsToggle || "";
        if (!targetId) return;

        event.preventDefault();

        if (closePanelIfOpen(targetId)) {
          clearHash();
          handleNavigation();
          return;
        }

        window.location.hash = `#${targetId}`;
      });
    }

    bindToggleCard(myProfileCard, "my-profile");
    bindToggleCard(attendanceCard, "attendance");

    toggleCards.forEach((card) => {
      if (card === myProfileCard || card === attendanceCard) return;
      bindToggleCard(card);
    });

    settingsRoot.querySelectorAll("form").forEach((form) => {
      form.addEventListener("submit", () => {
        const parentPanel = form.closest(
          ".settings-panel[data-settings-panel]",
        );
        if (!parentPanel) return;

        const panelId = parentPanel.dataset.settingsPanel || "";
        const baseAction = (
          form.getAttribute("action") || "settings.php"
        ).split("#")[0];
        form.setAttribute(
          "action",
          panelId ? `${baseAction}#${panelId}` : baseAction,
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

    handleNavigation();

    window.addEventListener("resize", () => {
      panels.forEach((panel) => {
        if (panel.classList.contains("is-visible")) {
          setPanelHeight(panel, true);
        }
      });
    });

    window.addEventListener("hashchange", handleNavigation);
  }

  window.addEventListener("DOMContentLoaded", bindSettingsPanelToggle);
})();

/* --- GLOBAL PASSWORD TOGGLE VISIBILITY --- */
(function globalPasswordToggleLogic() {
  function bindPasswordToggle() {
    document.querySelectorAll(".password-toggle-btn").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var targetId = btn.dataset.toggleTarget;
        if (!targetId) return;

        var input = document.getElementById(targetId);
        if (!input) return;

        var isPassword = input.type === "password";
        input.type = isPassword ? "text" : "password";

        var icon = btn.querySelector("i");
        if (icon) {
          icon.className = isPassword ? "fas fa-eye-slash" : "fas fa-eye";
          btn.classList.toggle("is-visible", !isPassword);
        }

        btn.setAttribute(
          "aria-label",
          isPassword ? "Sembunyikan password" : "Tampilkan password",
        );
        input.focus();
      });
    });
  }

  document.addEventListener("DOMContentLoaded", bindPasswordToggle);
})();

/* --- SETTINGS PROFILE PHOTO PREVIEW LOGIC --- */
(function settingsProfilePhotoLogic() {
  function bindSettingsProfilePhotoLogic() {
    var input = document.getElementById("profilePhotoInput");
    var preview = document.getElementById("settingsProfileAvatarPreview");
    var placeholder = document.getElementById(
      "settingsProfileAvatarPlaceholder",
    );
    var headerAvatarContainer = document.getElementById(
      "headerProfileAvatarContainer",
    );

    if (!(input instanceof HTMLInputElement) || !preview || !placeholder)
      return;

    function syncHeaderAvatar(imageSrc) {
      if (!headerAvatarContainer) return;

      var safeSrc = String(imageSrc || "").trim();
      var existingImage = headerAvatarContainer.querySelector(
        "#headerProfileAvatarImage",
      );
      var existingIcon = headerAvatarContainer.querySelector("i.fas");

      /* Show syncing animation */
      headerAvatarContainer.classList.add("is-syncing");

      if (safeSrc === "") {
        if (existingImage) existingImage.remove();
        if (!existingIcon) {
          var fallbackIcon = document.createElement("i");
          fallbackIcon.className = "fas fa-user";
          fallbackIcon.setAttribute("aria-hidden", "true");
          headerAvatarContainer.insertBefore(
            fallbackIcon,
            headerAvatarContainer.querySelector(".header-profile-avatar-sync"),
          );
        }
        headerAvatarContainer.classList.remove("is-syncing");
        return;
      }

      if (existingIcon) existingIcon.remove();

      var avatarImage =
        existingImage instanceof HTMLImageElement
          ? existingImage
          : document.createElement("img");

      avatarImage.id = "headerProfileAvatarImage";
      avatarImage.className = "header-profile-avatar-image";
      avatarImage.alt = "Profile";
      avatarImage.src = safeSrc;

      avatarImage.addEventListener(
        "load",
        function () {
          headerAvatarContainer.classList.remove("is-syncing");
        },
        { once: true },
      );
      avatarImage.addEventListener(
        "error",
        function () {
          headerAvatarContainer.classList.remove("is-syncing");
        },
        { once: true },
      );

      if (existingImage) {
        existingImage.src = safeSrc;
        headerAvatarContainer.classList.remove("is-syncing");
      } else {
        var syncIndicator = headerAvatarContainer.querySelector(
          ".header-profile-avatar-sync",
        );
        headerAvatarContainer.insertBefore(avatarImage, syncIndicator);
      }
    }

    input.addEventListener("change", function () {
      var file = input.files && input.files[0] ? input.files[0] : null;
      if (!file) return;

      var reader = new FileReader();
      reader.onload = function (event) {
        var imageSrc = String((event.target && event.target.result) || "");
        preview.src = imageSrc;
        preview.classList.remove("hidden");
        placeholder.classList.add("hidden");
        syncHeaderAvatar(imageSrc);
      };
      reader.readAsDataURL(file);
    });
  }

  document.addEventListener("DOMContentLoaded", bindSettingsProfilePhotoLogic);
})();

/* --- SETTINGS PROFILE SECURITY FLOW LOGIC --- */
(function settingsProfileSecurityFlowLogic() {
  function bindSettingsProfileSecurityFlow() {
    const profilePanel = document.querySelector(
      '[data-settings-panel="my-profile"]',
    );
    if (!profilePanel) return;

    const mainView = profilePanel.querySelector('[data-profile-view="main"]');
    const securityView = profilePanel.querySelector(
      '[data-profile-view="security"]',
    );
    if (!mainView || !securityView) return;

    const openSecurityButton = profilePanel.querySelector(
      '[data-open-profile-security="1"]',
    );
    const backButtons = profilePanel.querySelectorAll(
      '[data-return-profile-main="1"]',
    );
    const paneButtons = profilePanel.querySelectorAll(
      "[data-security-pane-target]",
    );
    const securityPanes = profilePanel.querySelectorAll("[data-security-pane]");
    const securityForms = profilePanel.querySelectorAll(
      "form[data-security-submit-pane]",
    );
    const viewStorageKey = "kasirPintarProfileSubview";
    const paneStorageKey = "kasirPintarProfileSecurityPane";
    const defaultPane = "reset-password";

    function refreshPanelHeight() {
      if (!profilePanel.classList.contains("is-visible")) return;
      window.requestAnimationFrame(() => {
        profilePanel.style.maxHeight = `${profilePanel.scrollHeight + 24}px`;
      });
    }

    function setView(viewName, persist = true) {
      const isSecurityView = viewName === "security";
      mainView.classList.toggle("is-active", !isSecurityView);
      securityView.classList.toggle("is-active", isSecurityView);

      if (persist) {
        window.sessionStorage.setItem(
          viewStorageKey,
          isSecurityView ? "security" : "main",
        );
      }

      refreshPanelHeight();
    }

    function setPane(paneName, persist = true) {
      let activePane = defaultPane;

      paneButtons.forEach((button) => {
        const isTarget = button.dataset.securityPaneTarget === paneName;
        button.classList.toggle("is-active", isTarget);
        if (isTarget) {
          activePane = paneName;
        }
      });

      securityPanes.forEach((pane) => {
        pane.classList.toggle(
          "is-active",
          pane.dataset.securityPane === activePane,
        );
      });

      /* Zero Trust: if leaving forgot-password pane via menu nav, clear session state
         so user cannot skip verification on re-entry. */
      if (activePane !== "forgot-password") {
        window.sessionStorage.removeItem("kasirPintarProfileSecurityPane");
      }

      /* Full-screen recovery mode: hide the nav buttons when forgot-password pane is active. */
      const securityMenu = profilePanel.querySelector(
        ".settings-security-menu",
      );
      if (securityMenu) {
        securityMenu.classList.toggle(
          "is-hidden",
          activePane === "forgot-password",
        );
      }

      if (persist) {
        window.sessionStorage.setItem(paneStorageKey, activePane);
      }

      refreshPanelHeight();
    }

    if (openSecurityButton) {
      openSecurityButton.addEventListener("click", (event) => {
        event.preventDefault();
        setView("security");
        setPane(window.sessionStorage.getItem(paneStorageKey) || defaultPane);
      });
    }

    backButtons.forEach((button) => {
      button.addEventListener("click", (event) => {
        event.preventDefault();
        setView("main");
      });
    });

    paneButtons.forEach((button) => {
      button.addEventListener("click", (event) => {
        event.preventDefault();
        setView("security");
        setPane(button.dataset.securityPaneTarget || defaultPane);
      });
    });

    /* Also bind back buttons inside forgot-password pane */
    profilePanel
      .querySelectorAll(".settings-security-back-btn")
      .forEach((btn) => {
        btn.addEventListener("click", (event) => {
          event.preventDefault();
          setView("security");
          setPane(btn.dataset.securityPaneTarget || defaultPane);
        });
      });

    /* Kembali in Reset Password / Reset Pertanyaan panes:
       Returns to the 3-menu security view (reset-password pane). */
    profilePanel
      .querySelectorAll('[data-action="reset-password-back"]')
      .forEach((btn) => {
        btn.addEventListener("click", (event) => {
          event.preventDefault();
          setPane("reset-password");
        });
      });

    /* Kembali inside Lupa Password (Stage 1 & Stage 2):
       MUST clear PHP session verification flag, then return to 3-menu security view. */
    profilePanel
      .querySelectorAll('[data-action="forgot-back-to-security"]')
      .forEach((btn) => {
        btn.addEventListener("click", (event) => {
          event.preventDefault();
          /* Immediately clear PHP verification session so user cannot skip re-verification on next entry */
          fetch("settings.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=forgot_password_reset_session&ajax=1",
          }).catch(function () {});
          /* Then navigate UI */
          const securityMenu = profilePanel.querySelector(
            ".settings-security-menu",
          );
          if (securityMenu) securityMenu.classList.remove("is-hidden");
          setPane("reset-password");
        });
      });

    /* Batal Stage 1 (Pilih Pertanyaan + Jawab):
       Total clear — wipe PHP session + sessionStorage + go to My Profile. */
    profilePanel
      .querySelectorAll('[data-action="forgot-batal-stage1"]')
      .forEach((btn) => {
        btn.addEventListener("click", (event) => {
          event.preventDefault();
          /* Immediately clear PHP session verification flag */
          fetch("settings.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=forgot_password_reset_session&ajax=1",
          }).catch(function () {});
          /* Clear local inputs and sessionStorage */
          const paneCard = btn.closest(".settings-security-pane-card");
          if (paneCard) {
            paneCard
              .querySelectorAll('input[type="text"], input[type="password"]')
              .forEach((input) => {
                input.value = "";
              });
            paneCard.querySelectorAll("select").forEach((select) => {
              select.selectedIndex = 0;
            });
            paneCard
              .querySelectorAll(".alert-danger, .alert-success")
              .forEach((alert) => {
                alert.remove();
              });
          }
          window.sessionStorage.removeItem("kasirPintarProfileSecurityPane");
          window.sessionStorage.removeItem("kasirPintarProfileSubview");
          const securityMenu = profilePanel.querySelector(
            ".settings-security-menu",
          );
          if (securityMenu) securityMenu.classList.remove("is-hidden");
          setView("main");
        });
      });

    /* Simpan Tanpa Perubahan (Stage 2 Set Password Baru):
       No DB write. Clears local state + PHP session + returns to My Profile. */
    profilePanel
      .querySelectorAll('[data-action="forgot-no-change"]')
      .forEach((btn) => {
        btn.addEventListener("click", (event) => {
          event.preventDefault();
          /* Clear PHP session verification flag — user must re-verify on next entry */
          fetch("settings.php", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: "action=forgot_password_reset_session&ajax=1",
          }).catch(function () {});
          /* Wipe all local sessionStorage flags */
          window.sessionStorage.removeItem("kasirPintarProfileSecurityPane");
          window.sessionStorage.removeItem("kasirPintarProfileSubview");
          /* Clear any inputs in the current form */
          const paneCard = btn.closest(".settings-security-pane-card");
          if (paneCard) {
            paneCard
              .querySelectorAll('input[type="text"], input[type="password"]')
              .forEach((input) => {
                input.value = "";
              });
            paneCard.querySelectorAll("select").forEach((select) => {
              select.selectedIndex = 0;
            });
            paneCard
              .querySelectorAll(".alert-danger, .alert-success")
              .forEach((alert) => {
                alert.remove();
              });
          }
          /* Show 3 nav buttons, exit to My Profile */
          const securityMenu = profilePanel.querySelector(
            ".settings-security-menu",
          );
          if (securityMenu) securityMenu.classList.remove("is-hidden");
          setView("main");
        });
      });

    /* Batal button for non-forgot-password security panes (Reset Password, Reset Pertanyaan).
       Clears inputs and exits to main profile. */
    profilePanel
      .querySelectorAll('[data-action="security-batal"]')
      .forEach((btn) => {
        btn.addEventListener("click", (event) => {
          event.preventDefault();
          /* Clear all input fields within this pane's form */
          const paneCard = btn.closest(".settings-security-pane-card");
          if (paneCard) {
            paneCard
              .querySelectorAll('input[type="text"], input[type="password"]')
              .forEach((input) => {
                input.value = "";
              });
            paneCard.querySelectorAll("select").forEach((select) => {
              /* Reset to first option (usually "-- Pilih --" placeholder) */
              select.selectedIndex = 0;
            });
            paneCard
              .querySelectorAll(".alert-danger, .alert-success")
              .forEach((alert) => {
                alert.remove();
              });
          }
          /* Exit to main profile view */
          setView("main");
        });
      });

    securityForms.forEach((form) => {
      form.addEventListener("submit", () => {
        window.sessionStorage.setItem(viewStorageKey, "security");
        window.sessionStorage.setItem(
          paneStorageKey,
          form.dataset.securitySubmitPane || defaultPane,
        );
      });
    });

    const myProfileMenuCard = document.querySelector(
      '.menu-card[data-settings-toggle="my-profile"]',
    );
    if (myProfileMenuCard) {
      myProfileMenuCard.addEventListener("click", () => {
        window.setTimeout(() => {
          if (!profilePanel.classList.contains("is-visible")) return;

          const desiredView =
            window.sessionStorage.getItem(viewStorageKey) === "security"
              ? "security"
              : "main";

          setView(desiredView, false);
          if (desiredView === "security") {
            setPane(
              window.sessionStorage.getItem(paneStorageKey) || defaultPane,
              false,
            );
          }
        }, 30);
      });
    }

    const initialView =
      window.sessionStorage.getItem(viewStorageKey) === "security"
        ? "security"
        : "main";

    setView(initialView, false);
    setPane(
      window.sessionStorage.getItem(paneStorageKey) || defaultPane,
      false,
    );
  }

  document.addEventListener(
    "DOMContentLoaded",
    bindSettingsProfileSecurityFlow,
  );
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
    if (!dateText) return "---";
    const normalized = String(dateText).replace(" ", "T");
    const date = new Date(normalized);
    if (Number.isNaN(date.getTime())) return "---";

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
      clockInTs: 0,
    };

    function recomputeDurationSeconds() {
      if (state.status === "Masuk") {
        // Prefer state.clockInTs (from API response), fall back to HTML data attribute
        const ts = state.clockInTs || Number(cashierWidget.dataset.clockInTs) || 0;
        if (ts > 0) {
          state.clockInTs = ts;
          state.durationSeconds = Math.max(
            0,
            Math.floor((Date.now() / 1000) - ts),
          );
          return;
        }
      }

      state.clockInTs = 0;
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

      if (toggleBtn) {
        toggleBtn.classList.remove("btn-minimalist-success", "btn-minimalist-danger");
        toggleBtn.classList.add(isOnline ? "btn-minimalist-danger" : "btn-minimalist-success");
      }

      const iconEl = document.getElementById("attendanceToggleIcon");
      if (iconEl) {
        iconEl.classList.remove("fa-sign-in-alt", "fa-sign-out-alt");
        iconEl.classList.add(isOnline ? "fa-sign-out-alt" : "fa-sign-in-alt");
      }
    }

    function hydrateAttendance(attendance) {
      state.status = attendance.status || "Pulang";
      state.clockIn = attendance.clock_in || "";
      state.clockOut = attendance.clock_out || "";
      state.totalHours = Number(attendance.total_hours || 0) || 0;
      state.clockInTs = Number(attendance.clock_in_ts || 0) || 0;
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

    const prefix = getCategoryPrefix(categorySelect.value);
    if (!prefix) return;

    enforceProductCodePrefix(categorySelect, codeInput, {
      clearWithoutPrefix: false,
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
      installProductCodeGuard("productCategory", "productCode", false);
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
    changeAmountEl.classList.toggle("insufficient", paid > 0 && paid < activeTotal);
    changeAmountEl.classList.toggle("sufficient", paid >= activeTotal && activeTotal > 0);
  }

  function renderCartRows(items) {
    if (!cartBody) return;

    if (!items || !items.length) {
      cartBody.innerHTML = `
        <tr class="transaction-empty-row">
          <td colspan="6" class="text-center">Belum ada item di keranjang.</td>
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

  /* ── Panel toggle ── */
  const menuCards = root.querySelectorAll(".menu-card-report");
  const panels = root.querySelectorAll(".reports-panel");
  if (menuCards.length && panels.length) {
    function openPanel(id) {
      panels.forEach((p) => {
        const show = p.dataset.reportsPanel === id;
        p.classList.toggle("is-visible", show);
        p.setAttribute("aria-hidden", show ? "false" : "true");
      });
      menuCards.forEach((c) => {
        c.classList.toggle("is-active", c.dataset.reportsToggle === id);
      });
      if (id) sessionStorage.setItem("kasirPintarActiveReportsPanel", id);
    }

    panels.forEach((p) => {
      p.classList.remove("is-visible");
      p.setAttribute("aria-hidden", "true");
    });

    menuCards.forEach((card) => {
      card.addEventListener("click", (e) => {
        e.preventDefault();
        const id = card.dataset.reportsToggle || "";
        openPanel(id);
        if (id) {
          const target = document.getElementById(id);
          if (target)
            target.scrollIntoView({ behavior: "smooth", block: "start" });
        }
      });
    });

    root.querySelectorAll("[data-reports-panel-close]").forEach((btn) => {
      btn.addEventListener("click", () => {
        openPanel("");
        window.sessionStorage.removeItem("kasirPintarActiveReportsPanel");
      });
    });

    const saved = sessionStorage.getItem("kasirPintarActiveReportsPanel") || "";
    if (saved) openPanel(saved);
  }

  /* ── Modal detail (data attributes) ── */
  function fillModal(btn) {
    const map = {
      rDetailId: btn.dataset.trxNum,
      rDetailCashier: btn.dataset.trxCashier,
      rDetailMethod: btn.dataset.trxMethod,
      rDetailTotal: btn.dataset.trxTotal,
      rDetailPaid: btn.dataset.trxPaid,
      rDetailChange: btn.dataset.trxChange,
    };
    for (const [id, val] of Object.entries(map)) {
      const el = document.getElementById(id);
      if (el) el.textContent = val || "-";
    }

    const tbody = document.getElementById("rDetailItemsBody");
    if (!tbody) return;
    let items = [];
    try {
      items = JSON.parse(btn.dataset.trxItems || "[]");
    } catch {}

    if (!items.length) {
      tbody.innerHTML =
        '<tr><td colspan="5" class="text-center text-muted">Tidak ada item.</td></tr>';
      document.getElementById("rDetailTotalValue").textContent =
        btn.dataset.trxTotal || "-";
      return;
    }

    let total = 0;
    tbody.innerHTML = items
      .map((it) => {
        const sub = it.subtotal || 0;
        total += sub;
        return `<tr>
        <td>${it.code || "-"}</td>
        <td>${it.name || "-"}</td>
        <td class="text-center">${it.qty || 0}</td>
        <td class="text-end">Rp ${Number(it.price || 0).toLocaleString("id-ID")}</td>
        <td class="text-end">Rp ${sub.toLocaleString("id-ID")}</td>
      </tr>`;
      })
      .join("");
    document.getElementById("rDetailTotalValue").textContent =
      `Rp ${total.toLocaleString("id-ID")}`;
  }

  /* wire up eye-button + trx-number-btn to open modal */
  root.addEventListener("click", (e) => {
    const eyeBtn = e.target.closest(".btn-reports-detail");
    if (eyeBtn) {
      const row = eyeBtn.closest("tr");
      const numBtn = row?.querySelector(".report-trx-number-btn");
      if (numBtn) {
        fillModal(numBtn);
        bootstrap.Modal.getOrCreateInstance(
          document.getElementById("reportDetailModal"),
        ).show();
      }
    }

    const numBtn = e.target.closest(".report-trx-number-btn");
    if (numBtn) {
      fillModal(numBtn);
      bootstrap.Modal.getOrCreateInstance(
        document.getElementById("reportDetailModal"),
      ).show();
    }
  });

  /* ── Client-side search filter (demo) ── */
  const searchInput = document.getElementById("reportSearchTx");
  if (searchInput) {
    searchInput.addEventListener("input", () => {
      const q = searchInput.value.trim().toLowerCase();
      document.querySelectorAll(".reports-trx-row").forEach((row) => {
        const text = row.textContent.toLowerCase();
        row.style.display = q && !text.includes(q) ? "none" : "";
      });
    });
  }
})();
