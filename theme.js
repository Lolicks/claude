'use strict';

// Случайная палитра на каждую загрузку страницы: если сайт открывают рядом
// несколько человек — у каждого свой цвет. Меняются только цвета (CSS-переменные),
// вёрстка и расположение элементов не трогаются.
// Подключается из <head> обычным <script src="theme.js"> (без defer/async),
// чтобы выполниться до отрисовки и не было мигания темы.
// Файл кэшируется, но Math.random() выполняется заново при каждой загрузке —
// поэтому тема всё равно меняется на каждом заходе.

(function () {
  // HSL -> "r, g, b" (для использования в rgba(var(--accent-rgb), ...)).
  function rgb(h, s, l) {
    s /= 100;
    l /= 100;
    var a = s * Math.min(l, 1 - l);
    var f = function (n) {
      var k = (n + h / 30) % 12;
      var c = l - a * Math.max(-1, Math.min(k - 3, 9 - k, 1));
      return Math.round(255 * c);
    };
    return f(0) + ', ' + f(8) + ', ' + f(4);
  }

  var hue = Math.floor(Math.random() * 360);
  var offsets = [40, 120, 150, 180, 210];
  var hue2 = (hue + offsets[Math.floor(Math.random() * offsets.length)]) % 360;

  var s = document.documentElement.style;
  s.setProperty('--accent-rgb', rgb(hue, 85, 60));
  s.setProperty('--accent2-rgb', rgb(hue2, 80, 58));
  s.setProperty('--bg', 'hsl(' + hue + ', 30%, 6%)');
  s.setProperty('--bg-2', 'hsl(' + hue + ', 28%, 9%)');
  s.setProperty('--panel', 'hsl(' + hue + ', 26%, 12%)');
  s.setProperty('--panel-2', 'hsl(' + hue + ', 24%, 16%)');
  s.setProperty('--border', 'hsl(' + hue + ', 22%, 24%)');
  s.setProperty('--code-bg', 'hsl(' + hue + ', 35%, 5%)');
  s.setProperty('--text', 'hsl(' + hue + ', 25%, 92%)');
  s.setProperty('--muted', 'hsl(' + hue + ', 16%, 65%)');
})();
