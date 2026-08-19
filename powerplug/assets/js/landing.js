/* PowerPlug Pro — Landing Page (Ads) behaviour.
   Vanilla JS, deferred, no dependencies. Loaded only on the
   "Landing Page — Category (Ads)" template. */
(function () {
	"use strict";

	/* Reveal-on-scroll */
	if ("IntersectionObserver" in window) {
		var revealIO = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) {
					en.target.classList.add("in");
					revealIO.unobserve(en.target);
				}
			});
		}, { threshold: 0.12 });
		document.querySelectorAll(".pp-lp-reveal").forEach(function (el) { revealIO.observe(el); });
	} else {
		document.querySelectorAll(".pp-lp-reveal").forEach(function (el) { el.classList.add("in"); });
	}

	/* Count-up stats */
	function countUp(el) {
		var target = parseInt(el.getAttribute("data-count"), 10) || 0;
		var suffix = el.getAttribute("data-suffix") || "";
		var dur = 1400, start = null;
		function step(ts) {
			if (!start) { start = ts; }
			var p = Math.min((ts - start) / dur, 1);
			el.textContent = Math.floor(p * target).toLocaleString() + suffix;
			if (p < 1) { requestAnimationFrame(step); }
			else { el.textContent = target.toLocaleString() + suffix; }
		}
		requestAnimationFrame(step);
	}
	if ("IntersectionObserver" in window) {
		var statIO = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (en.isIntersecting) {
					var num = en.target.querySelector(".pp-lp-stat__num");
					if (num) { countUp(num); }
					statIO.unobserve(en.target);
				}
			});
		}, { threshold: 0.5 });
		document.querySelectorAll(".pp-lp-stat").forEach(function (el) { statIO.observe(el); });
	}

	/* Honest same-day-dispatch line, tied to the real 5PM cut-off */
	var disp = document.querySelector("[data-pp-dispatch]");
	if (disp) {
		var now = new Date();
		var cut = new Date(now);
		cut.setHours(17, 0, 0, 0);
		var day = now.getDay();
		if (day === 0) {
			disp.textContent = "Order today — we dispatch first thing Monday.";
		} else if (now < cut) {
			var mins = Math.floor((cut - now) / 60000);
			disp.textContent = "Order within " + Math.floor(mins / 60) + "h " + (mins % 60) + "m for same-day Nairobi dispatch.";
		} else {
			disp.textContent = "Ordered after 5PM — we dispatch next business day.";
		}
	}

	/* Order form -> pre-filled WhatsApp message */
	var form = document.querySelector("[data-pp-order]");
	if (form) {
		form.addEventListener("submit", function (ev) {
			ev.preventDefault();
			var wa = form.getAttribute("data-wa") || "254708777192";
			var required = ["name", "phone", "county", "town", "model"];
			var ok = true;
			required.forEach(function (n) {
				var el = form.elements[n];
				if (el && !String(el.value).trim()) { el.classList.add("pp-lp-err"); ok = false; }
				else if (el) { el.classList.remove("pp-lp-err"); }
			});
			if (!ok) {
				if (form.elements.name) { form.elements.name.scrollIntoView({ behavior: "smooth", block: "center" }); }
				return;
			}
			var qty = Math.max(1, parseInt(form.elements.qty.value || "1", 10));
			var lines = [
				"NEW ORDER",
				"Name: " + form.elements.name.value,
				"Phone: " + form.elements.phone.value,
				"Location: " + form.elements.town.value + ", " + form.elements.county.value,
				"Item: " + form.elements.model.value,
				"Qty: " + qty,
				"Payment: " + form.elements.pay.value
			];
			var notes = String(form.elements.notes.value || "").trim();
			if (notes) { lines.push("Notes: " + notes); }
			window.open("https://wa.me/" + wa + "?text=" + encodeURIComponent(lines.join("\n")), "_blank", "noopener");
		});
	}

	/* ===== v2.16.0 premium layer ===== */
	/* Scroll progress bar */
	var ppProg = document.createElement("div");
	ppProg.className = "pp-lp-progress";
	document.body.appendChild(ppProg);
	function ppOnScrollProg() {
		var h = document.documentElement;
		var max = (h.scrollHeight - h.clientHeight) || 1;
		var y = window.pageYOffset || h.scrollTop || 0;
		ppProg.style.width = Math.min(100, Math.max(0, y / max * 100)) + "%";
	}
	window.addEventListener("scroll", ppOnScrollProg, { passive: true });
	ppOnScrollProg();

	/* Honest exit-intent (desktop). Nudges to the order form / WhatsApp. No fake discount. Once per session. */
	var ppOrder = document.getElementById("pp-lp-order");
	var ppSeen = false;
	try { if (window.sessionStorage && sessionStorage.getItem("ppLpExit") === "1") { ppSeen = true; } } catch (e) {}
	if (ppOrder && ppSeen === false) {
		var ppWaLink = document.querySelector('a[href*="wa.me/"]');
		var ppWaHref = ppWaLink ? ppWaLink.getAttribute("href") : "https://wa.me/254708777192";
		var ppExit = document.createElement("div");
		ppExit.className = "pp-lp-exit";
		ppExit.innerHTML = '<div class="pp-lp-exit__card"><button type="button" class="pp-lp-exit__close" aria-label="Close">×</button>' +
			'<span class="pp-lp-eyebrow">Before you go</span>' +
			'<h3>Order in 30 seconds</h3>' +
			'<p>Pay on delivery countrywide. Same-day Nairobi dispatch on orders before 5PM. We confirm stock and delivery on WhatsApp before anything ships.</p>' +
			'<div class="pp-lp-exit__btns">' +
			'<a class="pp-lp-btn pp-lp-btn--cta pp-lp-btn--block" href="#pp-lp-order">Order now</a>' +
			'<a class="pp-lp-btn pp-lp-btn--wa pp-lp-btn--block" href="' + ppWaHref + '" rel="nofollow noopener">Ask on WhatsApp</a>' +
			'</div></div>';
		document.body.appendChild(ppExit);
		function ppCloseExit() {
			ppExit.classList.remove("is-on");
			try { sessionStorage.setItem("ppLpExit", "1"); } catch (e) {}
		}
		ppExit.addEventListener("click", function (ev) {
			var t = ev.target;
			if (t === ppExit) { ppCloseExit(); return; }
			if (t.classList && t.classList.contains("pp-lp-exit__close")) { ppCloseExit(); return; }
			if (t.closest && t.closest("a")) { ppCloseExit(); }
		});
		document.addEventListener("mouseout", function (ev) {
			if (ppSeen === false && ev.clientY <= 0 && (ev.relatedTarget === null || ev.relatedTarget === undefined)) {
				ppSeen = true;
				ppExit.classList.add("is-on");
			}
		});
	}

})();
