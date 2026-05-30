// Плавающие сердечки на фоне — добавляются на любой странице, где подключён скрипт.
(function () {
  const layer = document.createElement("div");
  layer.className = "bg-hearts";
  layer.setAttribute("aria-hidden", "true");
  document.body.appendChild(layer);

  const emojis = ["💖", "💕", "💗", "💞", "🩷", "❤️", "✨"];
  const count = 16;

  for (let i = 0; i < count; i++) {
    const h = document.createElement("span");
    h.className = "bg-heart";
    h.textContent = emojis[Math.floor(Math.random() * emojis.length)];
    h.style.left = Math.random() * 100 + "vw";
    h.style.fontSize = Math.random() * 22 + 14 + "px";
    h.style.animationDuration = Math.random() * 12 + 10 + "s";
    h.style.animationDelay = -Math.random() * 22 + "s";
    h.style.opacity = (Math.random() * 0.4 + 0.2).toFixed(2);
    layer.appendChild(h);
  }
})();
