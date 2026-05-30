const noBtn = document.getElementById("noBtn");
const yesBtn = document.getElementById("yesBtn");
const result = document.getElementById("result");

// "Нет" нажать нельзя — кнопка убегает от курсора.
function runAway() {
  const padding = 20;
  const maxX = window.innerWidth - noBtn.offsetWidth - padding;
  const maxY = window.innerHeight - noBtn.offsetHeight - padding;
  const x = Math.max(padding, Math.random() * maxX);
  const y = Math.max(padding, Math.random() * maxY);

  noBtn.style.position = "fixed";
  noBtn.style.left = `${x}px`;
  noBtn.style.top = `${y}px`;
}

noBtn.addEventListener("mouseover", runAway);
noBtn.addEventListener("mousedown", (e) => {
  e.preventDefault();
  runAway();
});
noBtn.addEventListener("touchstart", (e) => {
  e.preventDefault();
  runAway();
});
noBtn.addEventListener("click", (e) => {
  e.preventDefault();
  runAway();
});

yesBtn.addEventListener("click", () => {
  result.hidden = false;
  yesBtn.disabled = true;
});
