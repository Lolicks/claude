'use strict';

// ======================= helpCisco — клиентская логика =======================

const CFG = window.HELPCISCO || {};
const NICK_KEY = 'helpcisco_nick';

function esc(s) {
  const d = document.createElement('div');
  d.textContent = String(s == null ? '' : s);
  return d.innerHTML;
}

function fmtTime(iso) {
  const d = new Date(iso);
  if (isNaN(d)) return '';
  const p = (n) => String(n).padStart(2, '0');
  return `${p(d.getDate())}.${p(d.getMonth() + 1)} ${p(d.getHours())}:${p(d.getMinutes())}`;
}

// ---- Ник: один на все чаты, помним между визитами ----
function getNick() {
  return localStorage.getItem(NICK_KEY) || '';
}
function setNick(name) {
  if (name) localStorage.setItem(NICK_KEY, name);
}

// Имя задаётся один раз. Пока ника нет — показываем поле имени;
// как только он задан — прячем поле и пишем только текст (как в обычном чате).
function applyNickUI() {
  const nick = getNick();
  document.querySelectorAll('.chat-form').forEach((form) => {
    const nameInput = form.querySelector('.chat-name');
    if (!nameInput) return;

    let chip = form.querySelector('.chat-as');
    if (!chip) {
      chip = document.createElement('div');
      chip.className = 'chat-as';
      nameInput.parentNode.insertBefore(chip, nameInput);
      chip.addEventListener('click', (e) => {
        const t = e.target;
        if (t && t.classList && t.classList.contains('chat-as-edit')) {
          e.preventDefault();
          form.classList.remove('named');
          nameInput.value = '';
          nameInput.focus();
        }
      });
    }

    if (nick) {
      nameInput.value = nick;
      form.classList.add('named');
      chip.innerHTML = 'Вы: <b>' + esc(nick) + '</b> · ' +
        '<a href="#" class="chat-as-edit">сменить имя</a>';
    } else {
      form.classList.remove('named');
      chip.textContent = '';
    }
  });
}

// ======================= Чаты (общий + под каждым постом) =======================

function appendMessage(log, m) {
  if (log.querySelector(`.msg[data-id="${m.id}"]`)) return; // не дублируем
  const div = document.createElement('div');
  div.className = 'msg';
  div.dataset.id = m.id;
  div.innerHTML =
    `<span class="msg-name">${esc(m.name)}</span>` +
    `<span class="msg-text">${esc(m.text).replace(/\n/g, '<br>')}</span>` +
    `<span class="msg-time">${esc(fmtTime(m.date))}</span>`;
  log.appendChild(div);
}

function isNearBottom(el) {
  return el.scrollHeight - el.scrollTop - el.clientHeight < 60;
}

async function pollRoom(log) {
  const room = log.dataset.room;
  const since = log.dataset.last || 0;
  try {
    const res = await fetch(`chat.php?room=${encodeURIComponent(room)}&since=${since}`);
    const data = await res.json();
    if (!data.ok || !Array.isArray(data.messages) || data.messages.length === 0) return;
    const stick = isNearBottom(log);
    data.messages.forEach((m) => {
      appendMessage(log, m);
      log.dataset.last = m.id;
    });
    if (stick) log.scrollTop = log.scrollHeight;
  } catch (_) {
    /* сеть моргнула — попробуем в следующий раз */
  }
}

function pollAllRooms() {
  document.querySelectorAll('.chat-log').forEach(pollRoom);
}

async function sendChat(form) {
  const room = form.dataset.room;
  const nameInput = form.querySelector('.chat-name');
  const textInput = form.querySelector('.chat-text');
  const text = textInput.value.trim();
  if (!text) return;

  // Имя нужно задать один раз. Нет имени — просим ввести и не отправляем.
  let name = (nameInput && nameInput.value.trim()) || getNick();
  if (!name) {
    if (nameInput) {
      form.classList.remove('named');
      nameInput.focus();
    }
    return;
  }
  setNick(name);
  applyNickUI();

  textInput.value = '';
  const log = document.querySelector(`.chat-log[data-room="${CSS.escape(room)}"]`);
  try {
    const res = await fetch('chat.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ room, name, text }),
    });
    const data = await res.json();
    if (data.ok && data.message) {
      if (log) {
        appendMessage(log, data.message);      // своё сообщение видно сразу
        log.dataset.last = data.message.id;
        log.scrollTop = log.scrollHeight;
      }
      pollAllRooms(); // сразу подтянем и чужие свежие сообщения
    }
  } catch (_) {
    textInput.value = text; // вернём текст, если не отправилось
  }
}

function initChats() {
  applyNickUI();
  document.querySelectorAll('.chat-form').forEach((form) => {
    form.addEventListener('submit', (e) => {
      e.preventDefault();
      sendChat(form);
    });
  });
  // Прокрутим логи вниз при загрузке.
  document.querySelectorAll('.chat-log').forEach((log) => {
    log.scrollTop = log.scrollHeight;
  });
  pollAllRooms();
  setInterval(pollAllRooms, 1500);          // частый опрос — чат «живой»
  window.addEventListener('focus', pollAllRooms); // вернулись на вкладку — сразу обновим
}

// ======================= Публикация своего конфига =======================

function initShare() {
  const form = document.getElementById('shareForm');
  if (!form) return;
  const status = document.getElementById('shareStatus');

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const author = document.getElementById('shareAuthor').value.trim() || getNick() || 'аноним';
    const title = document.getElementById('shareTitle').value.trim();
    const description = document.getElementById('shareDesc').value.trim();
    const config = document.getElementById('shareConfig').value.trim();
    if (!title || !config) {
      status.textContent = 'Нужны заголовок и конфиг.';
      return;
    }
    setNick(author);
    status.textContent = 'Публикуем…';
    try {
      const res = await fetch('posts.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ author, title, description, config }),
      });
      const data = await res.json();
      if (data.ok) {
        status.textContent = 'Опубликовано! Обновляем…';
        setTimeout(() => location.reload(), 600);
      } else {
        status.textContent = data.error || 'Не удалось опубликовать.';
      }
    } catch (_) {
      status.textContent = 'Ошибка сети.';
    }
  });
}

// ======================= ИИ-поддержка =======================

function initSupport() {
  const fab = document.getElementById('supportFab');
  const panel = document.getElementById('supportPanel');
  const close = document.getElementById('supportClose');
  const form = document.getElementById('supportForm');
  const input = document.getElementById('supportInput');
  const log = document.getElementById('supportLog');
  if (!fab || !panel || !form) return;

  const history = [];

  const toggle = (show) => {
    panel.hidden = !show;
    fab.classList.toggle('active', show);
    if (show) input.focus();
  };
  fab.addEventListener('click', () => toggle(panel.hidden));
  close.addEventListener('click', () => toggle(false));

  function add(role, text) {
    const div = document.createElement('div');
    div.className = 'msg ' + (role === 'user' ? 'me' : 'bot');
    div.innerHTML = `<span class="msg-text">${esc(text).replace(/\n/g, '<br>')}</span>`;
    log.appendChild(div);
    log.scrollTop = log.scrollHeight;
    return div;
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    const q = input.value.trim();
    if (!q) return;
    input.value = '';
    add('user', q);
    history.push({ role: 'user', content: q });

    const typing = add('bot', '…');
    typing.classList.add('typing');
    try {
      const res = await fetch('ai.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: q, history }),
      });
      const data = await res.json();
      const reply = (data && data.ok && data.reply) ? data.reply : 'Извините, не получилось ответить. Попробуйте ещё раз.';
      typing.classList.remove('typing');
      typing.querySelector('.msg-text').innerHTML = esc(reply).replace(/\n/g, '<br>');
      history.push({ role: 'assistant', content: reply });
      log.scrollTop = log.scrollHeight;
    } catch (_) {
      typing.classList.remove('typing');
      typing.querySelector('.msg-text').textContent = 'Ошибка сети. Попробуйте ещё раз.';
    }
  });
}

// ======================= Скрытые кнопки → скрытый мануал =======================
// Несколько незаметных способов попасть в скрытый раздел manual.php.
// Ключ ($MANUAL_KEY) приходит с сервера в window.HELPCISCO.manualKey.

function openManual(page) {
  const key = encodeURIComponent(CFG.manualKey || '');
  const p = encodeURIComponent(page || 'index');
  window.location.href = `manual.php?key=${key}&p=${p}`;
}

function initHiddenTriggers() {
  // 1) Konami-код: ↑ ↑ ↓ ↓ ← → ← → B A
  const seq = ['ArrowUp', 'ArrowUp', 'ArrowDown', 'ArrowDown',
    'ArrowLeft', 'ArrowRight', 'ArrowLeft', 'ArrowRight', 'b', 'a'];
  let pos = 0;
  document.addEventListener('keydown', (e) => {
    const k = e.key.length === 1 ? e.key.toLowerCase() : e.key;
    pos = (k === seq[pos]) ? pos + 1 : (k === seq[0] ? 1 : 0);
    if (pos === seq.length) {
      pos = 0;
      openManual('index');
    }
  });

  // 2) Тройной клик по «®» рядом с логотипом.
  const logo = document.getElementById('secretLogo');
  if (logo) {
    let clicks = 0;
    let timer = null;
    logo.addEventListener('click', (e) => {
      e.preventDefault();
      clicks += 1;
      clearTimeout(timer);
      timer = setTimeout(() => { clicks = 0; }, 700);
      if (clicks >= 3) {
        clicks = 0;
        openManual('index');
      }
    });
  }

  // 3) Версия в подвале: клик с зажатым Alt открывает скрытый раздел.
  const ver = document.getElementById('secretVersion');
  if (ver) {
    ver.addEventListener('click', (e) => {
      if (e.altKey) {
        e.preventDefault();
        openManual('index');
      }
    });
  }
}

// ======================= Старт =======================

document.addEventListener('DOMContentLoaded', () => {
  initChats();
  initShare();
  initSupport();
  initHiddenTriggers();
});
