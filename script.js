// Вопросы теста. Первый — особенный: кнопку "Нет" нажать нельзя.
const questions = [
  {
    text: "Ты меня любишь? 💖",
    type: "yesno",
  },
  {
    text: "Что ты почувствовала, когда мы познакомились?",
    type: "choice",
    options: ["Бабочки в животе 🦋", "Спокойствие и тепло ☺️", "Любопытство 🤔", "Сразу влюбилась 😍"],
  },
  {
    text: "Чем тебе больше всего нравится заниматься вместе?",
    type: "choice",
    options: ["Гулять под звёздами 🌙", "Смотреть фильмы 🍿", "Готовить вкусняшки 🍝", "Просто болтать обо всём 💬"],
  },
  {
    text: "Как сильно ты по мне скучаешь, когда мы не вместе?",
    type: "choice",
    options: ["Чуть-чуть 🙂", "Нормально так 😌", "Очень сильно 🥺", "Считаю минуты до встречи ⏳"],
  },
  {
    text: "Куда бы ты хотела отправиться со мной?",
    type: "choice",
    options: ["К морю 🌊", "В горы 🏔️", "В другой город ✈️", "Хоть на край света 🌍"],
  },
  {
    text: "Сколько сердечек я заслужил?",
    type: "choice",
    options: ["❤️", "❤️❤️", "❤️❤️❤️", "❤️❤️❤️❤️", "❤️❤️❤️❤️❤️"],
  },
];

const questionEl = document.getElementById("question");
const optionsEl = document.getElementById("options");
const buttonsEl = document.getElementById("buttons");
const resultEl = document.getElementById("result");
const progressBar = document.getElementById("progressBar");
const stepEl = document.getElementById("step");

let current = 0;
const answers = [];

function updateProgress() {
  const pct = (current / questions.length) * 100;
  progressBar.style.width = `${pct}%`;
}

// Перезапускает CSS-анимацию появления на элементе.
function animateIn(el) {
  el.classList.remove("anim-in");
  void el.offsetWidth;
  el.classList.add("anim-in");
}

function render() {
  updateProgress();
  const q = questions[current];
  if (stepEl) stepEl.textContent = `Вопрос ${current + 1} из ${questions.length}`;
  questionEl.textContent = q.text;
  animateIn(questionEl);
  optionsEl.innerHTML = "";
  buttonsEl.innerHTML = "";
  resultEl.hidden = true;

  if (q.type === "yesno") {
    renderYesNo();
  } else {
    renderChoice(q);
  }
}

function renderYesNo() {
  const yesBtn = document.createElement("button");
  yesBtn.className = "btn btn-yes";
  yesBtn.textContent = "Да";
  yesBtn.addEventListener("click", () => choose("Да 💕"));

  const noBtn = document.createElement("button");
  noBtn.className = "btn btn-no";
  noBtn.textContent = "Нет";

  // "Нет" нажать нельзя — кнопка убегает.
  const runAway = (e) => {
    if (e) e.preventDefault();
    const padding = 20;
    const maxX = window.innerWidth - noBtn.offsetWidth - padding;
    const maxY = window.innerHeight - noBtn.offsetHeight - padding;
    const x = Math.max(padding, Math.random() * maxX);
    const y = Math.max(padding, Math.random() * maxY);
    noBtn.style.position = "fixed";
    noBtn.style.left = `${x}px`;
    noBtn.style.top = `${y}px`;
  };
  noBtn.addEventListener("mouseover", runAway);
  noBtn.addEventListener("mousedown", runAway);
  noBtn.addEventListener("touchstart", runAway, { passive: false });
  noBtn.addEventListener("click", runAway);

  buttonsEl.appendChild(yesBtn);
  buttonsEl.appendChild(noBtn);
}

function renderChoice(q) {
  q.options.forEach((opt, i) => {
    const btn = document.createElement("button");
    btn.className = "btn btn-option opt-anim";
    btn.textContent = opt;
    btn.style.animationDelay = `${i * 70}ms`;
    btn.addEventListener("click", () => choose(opt));
    optionsEl.appendChild(btn);
  });
}

function choose(answer) {
  answers.push({ question: questions[current].text, answer });
  current++;
  if (current < questions.length) {
    render();
  } else {
    finish();
  }
}

function finish() {
  current = questions.length;
  updateProgress();
  if (stepEl) stepEl.textContent = "Готово 💝";
  questionEl.textContent = "Спасибо, любимая! 🥰";
  animateIn(questionEl);
  optionsEl.innerHTML = '<div class="heart-big">💖</div>';
  buttonsEl.innerHTML = "";
  resultEl.hidden = false;
  resultEl.innerHTML =
    "Жду тебя в понедельник 💖<br>где я покажу тебе свою любовь";
  burstHearts();
  saveAttempt();
}

// Салют из сердечек по центру экрана.
function burstHearts() {
  const emojis = ["💖", "💕", "💗", "❤️", "✨", "💞"];
  const cx = window.innerWidth / 2;
  const cy = window.innerHeight / 2;
  for (let i = 0; i < 26; i++) {
    const h = document.createElement("span");
    h.className = "burst-heart";
    h.textContent = emojis[Math.floor(Math.random() * emojis.length)];
    const angle = Math.random() * Math.PI * 2;
    const dist = 120 + Math.random() * 220;
    h.style.left = `${cx}px`;
    h.style.top = `${cy}px`;
    h.style.fontSize = `${16 + Math.random() * 18}px`;
    h.style.setProperty("--bx", `${Math.cos(angle) * dist}px`);
    h.style.setProperty("--by", `${Math.sin(angle) * dist}px`);
    h.style.animationDelay = `${Math.random() * 0.15}s`;
    document.body.appendChild(h);
    setTimeout(() => h.remove(), 1400);
  }
}

// Отправляем попытку на сервер. Каждая отправка — отдельная попытка.
function saveAttempt() {
  fetch("save.php", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({ answers }),
  })
    .then((r) => r.json())
    .then((res) => {
      if (!res || !res.ok) throw new Error("save failed");
    })
    .catch(() => {
      resultEl.innerHTML = "Не удалось отправить ответы 😢 Проверь интернет.";
    });
}

render();
