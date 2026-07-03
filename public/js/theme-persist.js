"use strict";

new MutationObserver(function () {
	localStorage.setItem("theme-version", document.body.getAttribute("data-theme-version"));
}).observe(document.body, { attributes: true, attributeFilter: ["data-theme-version"] });
