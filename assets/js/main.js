document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-auto-dismiss]').forEach((item) => {
    const delay = Number(item.dataset.autoDismiss || 20000);
    const close = item.querySelector('button');
    const dismiss = () => item.remove();
    close?.addEventListener('click', dismiss);
    window.setTimeout(dismiss, delay);
  });

  const toggle = document.querySelector('[data-nav-toggle]');
  const nav = document.querySelector('[data-nav]');
  toggle?.addEventListener('click', () => {
    nav?.classList.toggle('is-open');
    toggle.classList.toggle('is-open');
  });

  const timeout = Number(document.body.dataset.sessionTimeout || 0);
  const timeoutUrl = document.body.dataset.sessionTimeoutUrl;
  if (timeout > 0 && timeoutUrl) {
    let timer;
    const reset = () => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => {
        window.location.href = timeoutUrl;
      }, timeout * 1000);
    };
    ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach((eventName) => {
      document.addEventListener(eventName, reset, { passive: true });
    });
    reset();
  }
});
