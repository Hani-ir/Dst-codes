/*
 * LUBESTATION — client-side login gate for the static demo pages.
 * Uses the SAME Supabase project/auth as the real app (phone + password,
 * profiles.is_active, user_permissions.permission). Credentials and
 * access levels are managed entirely from the app's /admin → کاربران page;
 * nothing is hardcoded here.
 *
 * NOTE: this only covers/hides the page until login succeeds — it is a
 * UX gate for an internal demo, not a hard security boundary (the HTML
 * is still present in the DOM/served file). Real protection for private
 * data must stay in Supabase RLS, same as the production app.
 */
(function () {
  "use strict";

  var SUPABASE_URL = "https://c--2feaee82-5872-4a6b-9c61-1edae9f2e61d-prod.lovable.cloud";
  var SUPABASE_ANON_KEY = "sb_publishable_wMutLvW-Nd51WkWJ_pf4-A_L4N27o9-";
  var SESSION_KEY = "lubestation_gate_session_v1";

  var ROLE_LABELS = {
    admin: "مدیر سیستم",
    maintenance: "تعمیرات و نگهداری",
    dealership_isfahan: "نمایندگی اصفهان",
    dealership_shahrekord: "نمایندگی شهرکرد",
    dealership_yazd: "نمایندگی یزد",
    dealership_shiraz: "نمایندگی شیراز",
    dealership_tehran: "نمایندگی تهران",
    dealership_sirjan: "نمایندگی سیرجان"
  };

  function phoneToEmail(phone) { return phone + "@lubestation.local"; }
  function isValidPhone(phone) { return /^09\d{9}$/.test(phone); }

  function currentScript() {
    return document.currentScript || (function () {
      var scripts = document.getElementsByTagName("script");
      return scripts[scripts.length - 1];
    })();
  }

  var requiredRolesAttr = (currentScript().getAttribute("data-roles") || "any").trim();
  var requiredRoles = requiredRolesAttr === "any" ? null : requiredRolesAttr.split(",").map(function (s) { return s.trim(); });

  function hasAccess(roles) {
    if (roles.indexOf("admin") !== -1) return true; // admin always allowed
    if (!requiredRoles) return roles.length > 0; // "any" = any active account
    return roles.some(function (r) { return requiredRoles.indexOf(r) !== -1; });
  }

  function injectStyles() {
    var css = "\
      #lsGate{position:fixed;inset:0;z-index:2147483000;display:flex;align-items:center;justify-content:center;\
        background:radial-gradient(1200px 600px at 50% -10%,#0f1a18,#05070706 60%),#070908;\
        font-family:'Vazirmatn',-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;direction:rtl;padding:20px}\
      #lsGate *{box-sizing:border-box}\
      #lsGate .card{width:100%;max-width:360px;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.12);\
        border-radius:16px;padding:28px 24px;backdrop-filter:blur(6px)}\
      #lsGate h2{margin:0 0 4px;color:#f5f7f7;font-size:18px;font-weight:800}\
      #lsGate p.sub{margin:0 0 20px;color:#9aa5a3;font-size:12.5px;line-height:1.8}\
      #lsGate label{display:block;font-size:12.5px;color:#9aa5a3;margin:14px 0 6px}\
      #lsGate input{width:100%;padding:11px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.14);\
        background:rgba(0,0,0,.35);color:#f5f7f7;font-size:14px;font-family:inherit;direction:ltr;text-align:right}\
      #lsGate input:focus{outline:none;border-color:#22d3ee}\
      #lsGate button{width:100%;margin-top:20px;padding:12px;border:none;border-radius:10px;\
        background:#22c55e;color:#06130b;font-weight:800;font-size:14px;cursor:pointer;font-family:inherit}\
      #lsGate button:disabled{opacity:.6;cursor:default}\
      #lsGate .err{margin-top:14px;padding:10px 12px;border-radius:10px;background:rgba(239,68,68,.12);\
        border:1px solid rgba(239,68,68,.35);color:#fca5a5;font-size:12.5px;line-height:1.8;display:none}\
      #lsGate .err.show{display:block}\
      #lsBadge{position:fixed;bottom:16px;left:16px;z-index:2147483000;display:flex;align-items:center;gap:10px;\
        background:rgba(10,12,12,.9);border:1px solid rgba(255,255,255,.12);border-radius:999px;padding:8px 14px;\
        font-family:'Vazirmatn',sans-serif;direction:rtl;color:#f5f7f7;font-size:12.5px;backdrop-filter:blur(6px)}\
      #lsBadge b{color:#22d3ee}\
      #lsBadge button{border:none;background:none;color:#9aa5a3;cursor:pointer;font-size:12px;font-family:inherit;\
        text-decoration:underline;padding:0}\
    ";
    var style = document.createElement("style");
    style.textContent = css;
    document.head.appendChild(style);
  }

  function showGate() {
    document.documentElement.style.overflow = "hidden";
    var wrap = document.createElement("div");
    wrap.id = "lsGate";
    wrap.innerHTML =
      '<div class="card">' +
        '<h2>ورود به سامانه LUBESTATION</h2>' +
        '<p class="sub">دسترسی به این صفحه نیاز به ورود دارد. رمز عبور و سطح دسترسی شما در پنل مدیریت تعیین می‌شود.</p>' +
        '<label for="lsPhone">شماره موبایل</label>' +
        '<input id="lsPhone" type="tel" inputmode="numeric" placeholder="09xxxxxxxxx" autocomplete="username" maxlength="11">' +
        '<label for="lsPass">رمز عبور</label>' +
        '<input id="lsPass" type="password" placeholder="••••••••" autocomplete="current-password">' +
        '<button id="lsSubmit" type="button">ورود</button>' +
        '<div class="err" id="lsErr"></div>' +
      '</div>';
    document.body.appendChild(wrap);

    var btn = wrap.querySelector("#lsSubmit");
    var errBox = wrap.querySelector("#lsErr");
    var phoneEl = wrap.querySelector("#lsPhone");
    var passEl = wrap.querySelector("#lsPass");

    function setErr(msg) {
      errBox.textContent = msg;
      errBox.className = msg ? "err show" : "err";
    }

    function submit() {
      var phone = phoneEl.value.trim();
      var pass = passEl.value;
      setErr("");
      if (!isValidPhone(phone)) { setErr("شماره موبایل باید ۱۱ رقم باشد و با ۰۹ شروع شود."); return; }
      if (!pass) { setErr("رمز عبور را وارد کنید."); return; }
      btn.disabled = true; btn.textContent = "در حال ورود...";

      fetch(SUPABASE_URL + "/auth/v1/token?grant_type=password", {
        method: "POST",
        headers: { "Content-Type": "application/json", "apikey": SUPABASE_ANON_KEY },
        body: JSON.stringify({ email: phoneToEmail(phone), password: pass })
      })
        .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
        .then(function (res) {
          if (!res.ok || !res.body.access_token) {
            setErr("شماره موبایل یا رمز عبور اشتباه است.");
            btn.disabled = false; btn.textContent = "ورود";
            return;
          }
          var token = res.body.access_token;
          var userId = res.body.user && res.body.user.id;
          return Promise.all([
            fetch(SUPABASE_URL + "/rest/v1/profiles?id=eq." + userId + "&select=id,phone,full_name,is_active", {
              headers: { apikey: SUPABASE_ANON_KEY, Authorization: "Bearer " + token }
            }).then(function (r) { return r.json(); }),
            fetch(SUPABASE_URL + "/rest/v1/user_permissions?user_id=eq." + userId + "&select=permission", {
              headers: { apikey: SUPABASE_ANON_KEY, Authorization: "Bearer " + token }
            }).then(function (r) { return r.json(); })
          ]).then(function (results) {
            var profile = results[0] && results[0][0];
            var perms = (results[1] || []).map(function (row) { return row.permission; });
            if (!profile || !profile.is_active) {
              setErr("حساب شما غیرفعال است. با مدیر سیستم تماس بگیرید.");
              btn.disabled = false; btn.textContent = "ورود";
              return;
            }
            if (!hasAccess(perms)) {
              setErr("شما مجوز دسترسی به این صفحه را ندارید.");
              btn.disabled = false; btn.textContent = "ورود";
              return;
            }
            var sessionData = { token: token, name: profile.full_name, phone: profile.phone, roles: perms, at: Date.now() };
            try { sessionStorage.setItem(SESSION_KEY, JSON.stringify(sessionData)); } catch (e) {}
            reveal(sessionData);
          });
        })
        .catch(function () {
          setErr("ارتباط با سامانه برقرار نشد؛ دوباره تلاش کنید.");
          btn.disabled = false; btn.textContent = "ورود";
        });
    }

    btn.addEventListener("click", submit);
    passEl.addEventListener("keydown", function (e) { if (e.key === "Enter") submit(); });
  }

  function reveal(sessionData) {
    document.documentElement.style.overflow = "";
    var gate = document.getElementById("lsGate");
    if (gate) gate.remove();

    var badge = document.createElement("div");
    badge.id = "lsBadge";
    var roleLabel = ROLE_LABELS[sessionData.roles[0]] || sessionData.roles[0] || "";
    badge.innerHTML = '<span><b>' + (sessionData.name || sessionData.phone) + '</b> · ' + roleLabel + '</span><button id="lsLogout">خروج</button>';
    document.body.appendChild(badge);
    badge.querySelector("#lsLogout").addEventListener("click", function () {
      try { sessionStorage.removeItem(SESSION_KEY); } catch (e) {}
      location.reload();
    });
  }

  function init() {
    injectStyles();
    var raw = null;
    try { raw = sessionStorage.getItem(SESSION_KEY); } catch (e) {}
    if (raw) {
      try {
        var data = JSON.parse(raw);
        if (data && data.roles && hasAccess(data.roles) && Date.now() - data.at < 1000 * 60 * 60 * 8) {
          reveal(data);
          return;
        }
      } catch (e) {}
    }
    showGate();
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", init);
  } else {
    init();
  }
})();
