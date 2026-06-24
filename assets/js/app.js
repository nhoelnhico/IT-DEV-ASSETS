/* ============================================================
   Chromaesthetics IT Asset Manager — shared interactions
   Vanilla JS (no jQuery dependency). Safe on every page.
   - Off-canvas sidebar drawer (mobile) / collapse (desktop)
   - Light/Dark theme toggle (persisted in localStorage)
   - Scroll reveal with stagger, animated counters, ripple
   Honors prefers-reduced-motion.
   ============================================================ */
(function () {
    "use strict";

    var reduceMotion = window.matchMedia &&
        window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var desktop = function () {
        return window.matchMedia("(min-width: 992px)").matches;
    };

    function ready(fn) {
        if (document.readyState !== "loading") { fn(); }
        else { document.addEventListener("DOMContentLoaded", fn); }
    }

    /* ---------- Theme toggle ---------- */
    function applyThemeIcon(theme) {
        var btn = document.getElementById("themeToggle");
        if (!btn) return;
        var icon = btn.querySelector("i");
        if (!icon) return;
        icon.className = theme === "dark" ? "bi bi-sun-fill" : "bi bi-moon-stars-fill";
        btn.setAttribute("aria-label", theme === "dark" ? "Switch to light theme" : "Switch to dark theme");
        btn.setAttribute("title", theme === "dark" ? "Light mode" : "Dark mode");
    }

    function initTheme() {
        var current = document.documentElement.getAttribute("data-theme") || "light";
        applyThemeIcon(current);
        var btn = document.getElementById("themeToggle");
        if (!btn) return;
        btn.addEventListener("click", function () {
            var now = document.documentElement.getAttribute("data-theme") === "dark" ? "light" : "dark";
            document.documentElement.setAttribute("data-theme", now);
            try { localStorage.setItem("theme", now); } catch (e) {}
            applyThemeIcon(now);
        });
    }

    /* ---------- Sidebar drawer / collapse ---------- */
    function initSidebar() {
        var wrapper = document.getElementById("wrapper");
        var toggle = document.getElementById("sidebarToggle");
        var backdrop = document.querySelector(".sidebar-backdrop");
        if (!wrapper) return;

        function closeDrawer() { wrapper.classList.remove("sidebar-open"); }

        if (toggle) {
            toggle.addEventListener("click", function (e) {
                e.preventDefault();
                if (desktop()) {
                    wrapper.classList.toggle("sidebar-collapsed");
                } else {
                    wrapper.classList.toggle("sidebar-open");
                }
            });
        }
        if (backdrop) { backdrop.addEventListener("click", closeDrawer); }

        document.addEventListener("keydown", function (e) {
            if (e.key === "Escape") closeDrawer();
        });

        // close the drawer after tapping a nav link on mobile
        var links = document.querySelectorAll("#sidebar-wrapper .sidebar-nav a");
        links.forEach(function (a) {
            a.addEventListener("click", function () {
                if (!desktop()) closeDrawer();
            });
        });

        // reset drawer state when crossing the breakpoint
        window.addEventListener("resize", function () {
            if (desktop()) wrapper.classList.remove("sidebar-open");
        });
    }

    /* ---------- Scroll reveal (staggered) ---------- */
    function initReveal() {
        var items = Array.prototype.slice.call(document.querySelectorAll(".reveal"));
        if (!items.length) return;

        if (reduceMotion || !("IntersectionObserver" in window)) {
            items.forEach(function (el) { el.classList.add("in"); });
            return;
        }

        // stagger delay by position within the same parent group
        items.forEach(function (el) {
            var siblings = Array.prototype.filter.call(
                el.parentNode ? el.parentNode.children : [],
                function (c) { return c.classList && c.classList.contains("reveal"); }
            );
            var idx = siblings.indexOf(el);
            var delay = Math.min(idx * 70, 420);
            el.style.transitionDelay = delay + "ms";
        });

        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add("in");
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0, rootMargin: "0px 0px -10px 0px" });

        items.forEach(function (el) { io.observe(el); });

        // Safety net: a card taller than the viewport may never reach an
        // intersection threshold, so make sure nothing stays hidden.
        setTimeout(function () {
            items.forEach(function (el) { el.classList.add("in"); });
        }, 1600);
    }

    /* ---------- Animated counters ---------- */
    function animateCount(el) {
        var raw = (el.textContent || "").trim();
        if (!/^\d{1,3}(,\d{3})*$|^\d+$/.test(raw)) return;
        var hadCommas = raw.indexOf(",") !== -1;
        var target = parseInt(raw.replace(/,/g, ""), 10);
        if (isNaN(target) || target === 0) return;
        if (reduceMotion) return;

        var dur = 1100, start = null;
        function fmt(n) { return hadCommas ? n.toLocaleString("en-US") : String(n); }
        function step(ts) {
            if (start === null) start = ts;
            var p = Math.min((ts - start) / dur, 1);
            var eased = 1 - Math.pow(1 - p, 3);
            el.textContent = fmt(Math.round(target * eased));
            if (p < 1) requestAnimationFrame(step);
            else el.textContent = fmt(target);
        }
        el.textContent = fmt(0);
        requestAnimationFrame(step);
    }

    function initCounters() {
        var nums = document.querySelectorAll(".stat-card .h5-number, .mini-card .h5");
        if (!nums.length) return;
        if (reduceMotion || !("IntersectionObserver" in window)) return;
        var io = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateCount(entry.target);
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });
        nums.forEach(function (n) { io.observe(n); });
    }

    /* ---------- Button ripple ---------- */
    function initRipple() {
        if (reduceMotion) return;
        document.addEventListener("click", function (e) {
            var btn = e.target.closest ? e.target.closest(".btn") : null;
            if (!btn || btn.classList.contains("btn-close")) return;
            var rect = btn.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var span = document.createElement("span");
            span.className = "ripple";
            span.style.width = span.style.height = size + "px";
            span.style.left = (e.clientX - rect.left - size / 2) + "px";
            span.style.top = (e.clientY - rect.top - size / 2) + "px";
            btn.appendChild(span);
            setTimeout(function () { span.remove(); }, 600);
        });
    }

    ready(function () {
        initTheme();
        initSidebar();
        initReveal();
        initCounters();
        initRipple();
    });
})();
