"use strict";

document.addEventListener("DOMContentLoaded", function () {
	document.querySelectorAll(".dz-layout").forEach(function (el) {
		el.addEventListener("click", function () {
			var current = document.body.getAttribute("data-theme-version");
			localStorage.setItem("theme-version", current);
		});
	});
});
