document.addEventListener('DOMContentLoaded', () => {
  let customSelectId = 0;

  const initFlashMessages = (root = document) => {
    root.querySelectorAll('[data-auto-dismiss]').forEach((item) => {
      if (item.dataset.dismissReady === '1') return;
      item.dataset.dismissReady = '1';

      const delay = Number(item.dataset.autoDismiss || 20000);
      const close = item.querySelector('button');
      const dismiss = () => {
        item.style.opacity = '0';
        item.style.transform = 'translateX(60px)';
        setTimeout(() => item.remove(), 300);
      };
      close?.addEventListener('click', dismiss);
      setTimeout(dismiss, delay);
    });
  };

  const closeCustomSelects = (except = null) => {
    document.querySelectorAll('.academic-select.is-open').forEach((wrapper) => {
      if (wrapper === except) return;
      if (typeof wrapper.closeAcademicSelect === 'function') wrapper.closeAcademicSelect();
    });
  };

  const initCustomSelects = (root = document) => {
    root.querySelectorAll('select.form-select:not([multiple]):not([data-native-select]), select.rp-select:not([multiple]):not([data-native-select])').forEach((select) => {
      if (select.dataset.customReady === '1') return;
      select.dataset.customReady = '1';

      const wrapper = document.createElement('div');
      wrapper.className = 'academic-select';
      const isSmall = select.classList.contains('form-select-sm') || select.closest('.rp-select-sm');
      if (isSmall) wrapper.classList.add('is-small');
      if (select.classList.contains('rp-select') && !isSmall) wrapper.classList.add('is-tall');
      select.parentNode.insertBefore(wrapper, select);
      wrapper.appendChild(select);
      select.classList.add('academic-select-native');
      select.setAttribute('aria-hidden', 'true');
      select.tabIndex = -1;

      const trigger = document.createElement('button');
      trigger.className = 'academic-select-trigger';
      trigger.type = 'button';
      trigger.setAttribute('aria-haspopup', 'listbox');
      trigger.setAttribute('aria-expanded', 'false');
      trigger.disabled = select.disabled;

      const triggerText = document.createElement('span');
      triggerText.className = 'academic-select-value';
      trigger.appendChild(triggerText);

      const arrow = document.createElement('span');
      arrow.className = 'academic-select-arrow';
      arrow.setAttribute('aria-hidden', 'true');
      trigger.appendChild(arrow);

      const menu = document.createElement('div');
      menu.className = 'academic-select-menu';
      menu.setAttribute('role', 'listbox');
      menu.id = `academic-select-menu-${++customSelectId}`;
      menu.hidden = true;
      trigger.setAttribute('aria-controls', menu.id);

      const optionButtons = [...select.options].map((option) => {
        const button = document.createElement('button');
        button.className = 'academic-select-option';
        button.type = 'button';
        button.dataset.value = option.value;
        button.textContent = option.textContent.trim();
        button.disabled = option.disabled;
        button.setAttribute('role', 'option');
        menu.appendChild(button);
        return button;
      });

      wrapper.append(trigger, menu);

      if (select.id) {
        const label = [...document.querySelectorAll('label')].find((item) => item.htmlFor === select.id);
        trigger.id = `${select.id}-trigger`;
        if (label) {
          label.htmlFor = trigger.id;
          trigger.setAttribute('aria-label', label.textContent.trim());
        }
      }

      const syncSelection = () => {
        const selectedOption = select.options[select.selectedIndex];
        triggerText.textContent = selectedOption?.textContent.trim() || 'Chọn giá trị';
        optionButtons.forEach((button) => {
          const selected = button.dataset.value === select.value;
          button.classList.toggle('is-selected', selected);
          button.setAttribute('aria-selected', selected ? 'true' : 'false');
        });
      };

      const close = () => {
        wrapper.classList.remove('is-open');
        trigger.setAttribute('aria-expanded', 'false');
        menu.hidden = true;
        menu.removeAttribute('style');
        if (menu.parentNode !== wrapper) wrapper.appendChild(menu);
      };
      wrapper.closeAcademicSelect = close;

      const positionMenu = () => {
        const rect = trigger.getBoundingClientRect();
        const gap = 6;
        const viewportPadding = 8;
        const availableBelow = window.innerHeight - rect.bottom - gap - viewportPadding;
        const availableAbove = rect.top - gap - viewportPadding;
        const openUpward = availableBelow < 160 && availableAbove > availableBelow;
        const availableHeight = openUpward ? availableAbove : availableBelow;
        const width = Math.min(rect.width, window.innerWidth - viewportPadding * 2);
        const left = Math.min(
          Math.max(viewportPadding, rect.left),
          Math.max(viewportPadding, window.innerWidth - width - viewportPadding)
        );

        menu.style.position = 'fixed';
        menu.style.left = `${left}px`;
        menu.style.width = `${width}px`;
        menu.style.maxHeight = `${Math.max(110, Math.min(260, availableHeight))}px`;
        if (openUpward) {
          menu.style.top = 'auto';
          menu.style.bottom = `${window.innerHeight - rect.top + gap}px`;
        } else {
          menu.style.top = `${rect.bottom + gap}px`;
          menu.style.bottom = 'auto';
        }
      };

      trigger.addEventListener('click', (event) => {
        event.stopPropagation();
        const shouldOpen = !wrapper.classList.contains('is-open');
        closeCustomSelects(wrapper);
        if (!shouldOpen) {
          close();
          return;
        }

        wrapper.classList.add('is-open');
        trigger.setAttribute('aria-expanded', 'true');
        (select.closest('dialog') || document.body).appendChild(menu);
        menu.hidden = false;
        positionMenu();
      });

      optionButtons.forEach((button) => {
        button.addEventListener('click', () => {
          select.value = button.dataset.value;
          select.dispatchEvent(new Event('change', { bubbles: true }));
          syncSelection();
          close();
          trigger.focus();
        });
      });

      select.addEventListener('change', syncSelection);
      syncSelection();
    });
  };

  const initBulkStudentAssignments = (root = document) => {
    root.querySelectorAll('[data-bulk-student-assignment]').forEach((section) => {
      if (section.dataset.bulkReady === '1') return;
      section.dataset.bulkReady = '1';

      const classSelect = section.querySelector('[data-bulk-class]');
      const searchInput = section.querySelector('[data-student-search]');
      const options = [...section.querySelectorAll('[data-student-option]')];
      const selectedCount = section.querySelector('[data-selected-count]');
      const submitButton = section.querySelector('[data-assign-students]');

      const updateCount = () => {
        const count = options.filter((option) => option.querySelector('[data-student-check]')?.checked).length;
        if (selectedCount) selectedCount.textContent = `Đã chọn ${count} sinh viên`;
        if (submitButton) submitButton.disabled = count === 0 || !classSelect?.value;
      };

      const applySearch = () => {
        const keyword = (searchInput?.value || '').trim().toLocaleLowerCase('vi');
        options.forEach((option) => {
          const text = option.dataset.searchText || '';
          option.classList.toggle('is-hidden', keyword !== '' && !text.includes(keyword));
        });
      };

      const refreshClass = () => {
        const classId = classSelect?.value || '';
        options.forEach((option) => {
          const checkbox = option.querySelector('[data-student-check]');
          const status = option.querySelector('[data-student-status]');
          if (!checkbox) return;

          const assignedClasses = (checkbox.dataset.classIds || '').split(',').filter(Boolean);
          const isAssigned = classId !== '' && assignedClasses.includes(classId);
          checkbox.checked = false;
          checkbox.disabled = classId === '' || isAssigned;
          option.classList.toggle('is-assigned', isAssigned);
          if (status) status.textContent = classId === '' ? 'Chưa có lớp' : (isAssigned ? 'Đã thuộc lớp' : 'Có thể gán');
        });
        applySearch();
        updateCount();
      };

      classSelect?.addEventListener('change', refreshClass);
      searchInput?.addEventListener('input', applySearch);
      options.forEach((option) => {
        option.querySelector('[data-student-check]')?.addEventListener('change', updateCount);
      });
      section.querySelector('[data-select-visible]')?.addEventListener('click', () => {
        options.forEach((option) => {
          const checkbox = option.querySelector('[data-student-check]');
          if (checkbox && !checkbox.disabled && !option.classList.contains('is-hidden')) checkbox.checked = true;
        });
        updateCount();
      });
      section.querySelector('[data-clear-selection]')?.addEventListener('click', () => {
        options.forEach((option) => {
          const checkbox = option.querySelector('[data-student-check]');
          if (checkbox) checkbox.checked = false;
        });
        updateCount();
      });

      refreshClass();
    });
  };

  const initEditorModals = (root = document) => {
    root.querySelectorAll('[data-editor-modal]').forEach((modal) => {
      if (modal.dataset.modalReady === '1') return;
      modal.dataset.modalReady = '1';

      if (modal.dataset.openOnLoad === '1' && typeof modal.showModal === 'function') {
        requestAnimationFrame(() => modal.showModal());
      }

      modal.addEventListener('click', (event) => {
        if (event.target === modal) modal.close();
      });
      modal.addEventListener('close', () => {
        const nextUrl = new URL(window.location.href);
        nextUrl.searchParams.delete('create');
        nextUrl.searchParams.delete('edit');
        window.history.replaceState(null, '', nextUrl);
      });
    });
  };

  const refreshMainContent = async (form, submitter) => {
    const formData = new FormData(form);
    if (submitter?.name) formData.append(submitter.name, submitter.value);
    const actionUrl = form.getAttribute('action') || window.location.href;
    const asyncId = form.dataset.asyncId || '';
    const preservedValues = [...form.querySelectorAll('[data-preserve-value][name]')].map((field) => ({
      name: field.name,
      value: field.value,
    }));

    const response = await fetch(actionUrl, {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
    });
    const html = await response.text();
    const nextDocument = new DOMParser().parseFromString(html, 'text/html');
    const nextMain = nextDocument.querySelector('.main-content');
    const currentMain = document.querySelector('.main-content');

    if (!response.ok || !nextMain || !currentMain) {
      window.location.href = response.url || window.location.href;
      return;
    }

    const scrollPosition = window.scrollY;
    currentMain.innerHTML = nextMain.innerHTML;
    document.title = nextDocument.title || document.title;
    window.history.replaceState(null, '', response.url);

    const nextForm = asyncId ? currentMain.querySelector(`[data-async-id="${asyncId}"]`) : null;
    preservedValues.forEach(({ name, value }) => {
      const field = nextForm?.querySelector(`[name="${name}"]`);
      if (field) field.value = value;
    });

    initFlashMessages(currentMain);
    initCustomSelects(currentMain);
    initBulkStudentAssignments(currentMain);
    initEditorModals(currentMain);
    requestAnimationFrame(() => window.scrollTo(0, scrollPosition));
  };

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-async-form]');
    if (!form || event.defaultPrevented || form.dataset.submitting === '1') return;

    event.preventDefault();
    form.dataset.submitting = '1';
    const submitter = event.submitter;
    const originalText = submitter?.textContent || '';
    if (submitter) {
      submitter.disabled = true;
      submitter.classList.add('is-loading');
      submitter.textContent = 'Đang xử lý...';
    }

    try {
      await refreshMainContent(form, submitter);
    } catch (error) {
      form.dataset.submitting = '0';
      if (submitter) {
        submitter.disabled = false;
        submitter.classList.remove('is-loading');
        submitter.textContent = originalText;
      }
      HTMLFormElement.prototype.submit.call(form);
    }
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('.academic-select')) closeCustomSelects();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') closeCustomSelects();
  });
  window.addEventListener('resize', () => closeCustomSelects());
  window.addEventListener('scroll', () => closeCustomSelects());

  /* ===== Sidebar toggle (mobile) ===== */
  const sidebar = document.querySelector('[data-sidebar]');
  const overlay = document.querySelector('[data-sidebar-overlay]');
  const openBtn = document.querySelector('[data-sidebar-toggle]');
  const closeBtn = document.querySelector('[data-sidebar-close]');

  const openSidebar = () => {
    sidebar?.classList.add('is-open');
    overlay?.classList.add('is-open');
    document.body.style.overflow = 'hidden';
  };
  const closeSidebar = () => {
    sidebar?.classList.remove('is-open');
    overlay?.classList.remove('is-open');
    document.body.style.overflow = '';
  };

  openBtn?.addEventListener('click', openSidebar);
  closeBtn?.addEventListener('click', closeSidebar);
  overlay?.addEventListener('click', closeSidebar);

  /* ===== Session timeout ===== */
  const timeout = Number(document.body.dataset.sessionTimeout || 0);
  const timeoutUrl = document.body.dataset.sessionTimeoutUrl;
  if (timeout > 0 && timeoutUrl) {
    let timer;
    const reset = () => {
      clearTimeout(timer);
      timer = setTimeout(() => { window.location.href = timeoutUrl; }, timeout * 1000);
    };
    ['click', 'keydown', 'mousemove', 'scroll', 'touchstart'].forEach((eventName) => {
      document.addEventListener(eventName, reset, { passive: true });
    });
    reset();
  }

  initFlashMessages();
  initCustomSelects();
  initBulkStudentAssignments();
  initEditorModals();

  document.querySelectorAll('.stat-card, .card-panel').forEach((element, index) => {
    element.style.opacity = '0';
    element.style.transform = 'translateY(16px)';
    setTimeout(() => {
      element.style.transition = 'opacity .4s ease, transform .4s ease';
      element.style.opacity = '1';
      element.style.transform = 'translateY(0)';
    }, 60 + index * 50);
  });
});
