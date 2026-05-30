const STORAGE_KEY = "loveQuizAttempts";

const attemptsEl = document.getElementById("attempts");
const emptyEl = document.getElementById("empty");
const clearBtn = document.getElementById("clearBtn");

function loadAttempts() {
  try {
    return JSON.parse(localStorage.getItem(STORAGE_KEY)) || [];
  } catch {
    return [];
  }
}

function formatDate(iso) {
  const d = new Date(iso);
  return d.toLocaleString("ru-RU", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function render() {
  const attempts = loadAttempts();
  attemptsEl.innerHTML = "";

  if (attempts.length === 0) {
    emptyEl.hidden = false;
    return;
  }
  emptyEl.hidden = true;

  // Новые попытки сверху. Каждая попытка — отдельная карточка.
  attempts
    .slice()
    .reverse()
    .forEach((attempt, idx) => {
      const number = attempts.length - idx;
      const card = document.createElement("section");
      card.className = "attempt";

      const head = document.createElement("div");
      head.className = "attempt-head";
      head.innerHTML = `<span class="attempt-num">Попытка №${number}</span>
        <span class="attempt-date">${formatDate(attempt.date)}</span>`;
      card.appendChild(head);

      const list = document.createElement("ol");
      list.className = "answers";
      attempt.answers.forEach((a) => {
        const li = document.createElement("li");
        li.innerHTML = `<span class="q">${a.question}</span>
          <span class="a">${a.answer}</span>`;
        list.appendChild(li);
      });
      card.appendChild(list);

      attemptsEl.appendChild(card);
    });
}

clearBtn.addEventListener("click", () => {
  if (confirm("Удалить все попытки? Это нельзя отменить.")) {
    localStorage.removeItem(STORAGE_KEY);
    render();
  }
});

render();
