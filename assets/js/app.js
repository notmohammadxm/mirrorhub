document.addEventListener('DOMContentLoaded', () => {
    /* ============================================================
       SVG Icons (inline)
       ============================================================ */
    const Icons = {
        check: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        x: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        alert: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        info: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>',
        trash: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg>',
        question: '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
    };

    const CloseBtn = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';

    /* ============================================================
       Modal System
       ============================================================ */
    const Modal = {
        _current: null,

        open(options = {}) {
            const {
                title = '',
                message = '',
                html = '',
                type = 'info',
                confirmText = 'تأیید',
                cancelText = 'انصراف',
                showCancel = true,
                onConfirm = null,
                onCancel = null,
                wide = false,
                icon = null,
            } = options;

            this.close(true);

            const iconMap = {
                success: Icons.check,
                error: Icons.x,
                warning: Icons.alert,
                danger: Icons.alert,
                info: Icons.info,
                question: Icons.question,
            };

            const iconHtml = icon || iconMap[type] || Icons.info;

            const overlay = document.createElement('div');
            overlay.className = 'mh-modal-overlay';
            overlay.setAttribute('role', 'dialog');
            overlay.setAttribute('aria-modal', 'true');

            const bodyHtml = html || (message ? `<p>${message}</p>` : '');

            overlay.innerHTML = `
                <div class="mh-modal${wide ? ' wide' : ''}">
                    <div class="mh-modal-header ${type}">
                        <span class="mh-modal-icon ${type}">${iconHtml}</span>
                        <span class="mh-modal-title">${title}</span>
                        <button type="button" class="mh-modal-close" aria-label="بستن">${CloseBtn}</button>
                    </div>
                    <div class="mh-modal-body">${bodyHtml}</div>
                    <div class="mh-modal-actions">
                        ${showCancel ? `<button type="button" class="btn btn-outline" data-modal-cancel>${cancelText}</button>` : ''}
                        <button type="button" class="btn btn-primary" data-modal-confirm>${confirmText}</button>
                    </div>
                </div>
            `;

            document.body.appendChild(overlay);
            document.body.style.overflow = 'hidden';
            this._current = overlay;

            requestAnimationFrame(() => {
                const confirmBtn = overlay.querySelector('[data-modal-confirm]');
                if (confirmBtn) confirmBtn.focus();
            });

            const close = (confirmed) => {
                if (confirmed && typeof onConfirm === 'function') {
                    const result = onConfirm(overlay);
                    if (result === false) return;
                } else if (!confirmed && typeof onCancel === 'function') {
                    onCancel();
                }
                this.close();
            };

            overlay.querySelector('.mh-modal-close').addEventListener('click', () => close(false));
            overlay.querySelector('[data-modal-confirm]').addEventListener('click', () => close(true));

            const cancelBtn = overlay.querySelector('[data-modal-cancel]');
            if (cancelBtn) cancelBtn.addEventListener('click', () => close(false));

            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) close(false);
            });

            const escHandler = (e) => {
                if (e.key === 'Escape') {
                    document.removeEventListener('keydown', escHandler);
                    close(false);
                }
            };
            document.addEventListener('keydown', escHandler);

            return overlay;
        },

        close(immediate = false) {
            if (!this._current) return;
            const overlay = this._current;
            this._current = null;

            if (immediate) {
                overlay.remove();
                document.body.style.overflow = '';
                return;
            }

            overlay.classList.add('closing');
            document.body.style.overflow = '';
            setTimeout(() => overlay.remove(), 220);
        },
    };

    window.MHModal = Modal;

    window.showAlert = (message, type = 'info', title = null) => {
        const titles = {
            success: 'انجام شد',
            error: 'خطا',
            warning: 'هشدار',
            info: 'اطلاع',
        };
        Modal.open({
            title: title || titles[type] || 'اطلاع',
            message,
            type,
            confirmText: 'باشه',
            showCancel: false,
        });
    };

    window.showConfirm = (options = {}) => {
        return new Promise((resolve) => {
            Modal.open({
                title: options.title || 'تأیید عملیات',
                message: options.message || 'آیا از انجام این عملیات مطمئن هستید؟',
                type: options.type || 'question',
                confirmText: options.confirmText || 'بله، تأیید',
                cancelText: options.cancelText || 'انصراف',
                onConfirm: () => resolve(true),
                onCancel: () => resolve(false),
            });
        });
    };

    /* ============================================================
       Delete Forms with data-confirm
       ============================================================ */
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            const message = form.dataset.confirm || 'آیا از حذف مطمئن هستید؟';
            const confirmed = await showConfirm({
                title: 'تأیید حذف',
                message,
                type: 'danger',
                confirmText: 'بله، حذف کن',
                cancelText: 'انصراف',
            });
            if (confirmed) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
    });

    /* Fallback: legacy onsubmit confirm */
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.addEventListener('submit', async (e) => {
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            const message = form.dataset.confirm || 'آیا از انجام این عملیات مطمئن هستید؟';
            const confirmed = await showConfirm({
                title: 'تأیید عملیات',
                message,
                type: 'danger',
                confirmText: 'بله، انجام بده',
            });
            if (confirmed) {
                form.dataset.confirmed = '1';
                form.submit();
            }
        });
        form.removeAttribute('onsubmit');
    });

    /* ============================================================
       Auto-dismiss Alerts
       ============================================================ */
    document.querySelectorAll('.alert').forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            alert.style.pointerEvents = 'none';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });

    /* ============================================================
       Category Suggestion Chips
       ============================================================ */
    const chips = document.querySelectorAll('.suggestion-chip');
    const categoryInput = document.getElementById('category_name');
    if (chips.length > 0 && categoryInput) {
        chips.forEach(chip => {
            chip.addEventListener('click', () => {
                categoryInput.value = chip.dataset.value;
                chips.forEach(c => {
                    c.style.borderColor = '';
                    c.style.color = '';
                    c.style.background = '';
                });
                chip.style.borderColor = 'var(--accent)';
                chip.style.color = 'var(--accent)';
                chip.style.background = 'var(--accent-soft)';
                categoryInput.focus();
            });
        });
        categoryInput.addEventListener('input', () => {
            const v = categoryInput.value.trim();
            chips.forEach(c => {
                const m = c.dataset.value === v;
                c.style.borderColor = m ? 'var(--accent)' : '';
                c.style.color = m ? 'var(--accent)' : '';
                c.style.background = m ? 'var(--accent-soft)' : '';
            });
        });
    }

    /* ============================================================
       Suggestion Parent/Child Category Picker
       ============================================================ */
    const parentCategory = document.getElementById('parent_category_id');
    const childCategory = document.getElementById('category_id');
    if (parentCategory && childCategory) {
        const syncChildCategories = () => {
            const parentId = parentCategory.value;
            let visible = 0;
            childCategory.querySelectorAll('option[data-parent]').forEach(option => {
                const show = !!parentId && option.dataset.parent === parentId;
                option.hidden = !show;
                if (!show && option.selected) option.selected = false;
                if (show) visible++;
            });
            childCategory.disabled = !parentId || visible === 0;
            if (!parentId) {
                childCategory.value = '';
                childCategory.querySelector('option:not([data-parent])').textContent = 'ابتدا والد را انتخاب کنید...';
            } else {
                childCategory.querySelector('option:not([data-parent])').textContent =
                    visible ? 'انتخاب زیر‌دسته...' : 'این والد زیر‌دسته‌ای ندارد';
            }
        };
        parentCategory.addEventListener('change', syncChildCategories);
        syncChildCategories();
    }

    /* ============================================================
       Auto Slug Generation
       ============================================================ */
    const nameFa = document.getElementById('name_fa');
    const nameEn = document.getElementById('name_en');
    const slugField = document.getElementById('slug');

    if (slugField) {
        let slugTouched = slugField.value.trim() !== '';
        slugField.addEventListener('input', () => { slugTouched = true; });

        const generateSlug = () => {
            if (slugTouched) return;
            const source = (nameEn && nameEn.value.trim()) || (nameFa && nameFa.value.trim()) || '';
            slugField.value = source
                .trim()
                .toLowerCase()
                .replace(/[^\p{L}\d]+/gu, '-')
                .replace(/^-+|-+$/g, '');
        };

        if (nameFa) nameFa.addEventListener('input', generateSlug);
        if (nameEn) nameEn.addEventListener('input', generateSlug);
    }

    /* ============================================================
       Category Picker Validation
       ============================================================ */
    const categoryForm = document.querySelector('form[data-require-category]');
    if (categoryForm) {
        categoryForm.addEventListener('submit', (e) => {
            const checked = categoryForm.querySelectorAll('input[name="category_ids[]"]:checked');
            if (checked.length === 0) {
                e.preventDefault();
                showAlert('حداقل یک دسته‌بندی را انتخاب کنید.', 'warning', 'انتخاب دسته‌بندی');
            }
        });
    }

    /* ============================================================
       Multi Protocol Picker
       ============================================================ */
    document.querySelectorAll('.protocol-picker').forEach(picker => {
        const customCheckbox = picker.querySelector('input[name="protocols[]"][value="custom"]');
        const customInput = picker.querySelector('.custom-protocol-input');
        if (customCheckbox && customInput) {
            const sync = () => {
                customInput.disabled = !customCheckbox.checked;
                if (!customCheckbox.checked) customInput.value = '';
                else customInput.focus();
            };
            customCheckbox.addEventListener('change', sync);
            sync();
        }
    });

    /* ============================================================
       Focus Search on '/' key
       ============================================================ */
    document.addEventListener('keydown', (e) => {
        if (e.key === '/' && !['INPUT', 'TEXTAREA', 'SELECT'].includes(document.activeElement.tagName)) {
            const searchInput = document.querySelector('.search-form input[name="q"]');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        }
    });

    /* ============================================================
       Review Request Modal
       ============================================================ */
    document.querySelectorAll('[data-review-request]').forEach(btn => {
        btn.addEventListener('click', () => {
            const id = btn.dataset.id;
            const nameFa = btn.dataset.nameFa || '';
            const nameEn = btn.dataset.nameEn || '';
            const url = btn.dataset.url || '';
            const protocol = btn.dataset.protocol || '';
            const category = btn.dataset.category || '';
            const description = btn.dataset.description || '';
            const status = btn.dataset.status || 'pending';
            const note = btn.dataset.note || '';
            const mirrorId = btn.dataset.mirrorId || '';
            const csrf = btn.dataset.csrf || '';

            const isReviewed = status === 'reviewed';

            const html = `
                <div style="display:flex;flex-direction:column;gap:0.9rem;">
                    <div>
                        <p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.15rem;">نام میرور</p>
                        <p style="color:var(--text-primary);font-weight:600;font-size:0.95rem;">${escapeHtml(nameFa)}</p>
                        ${nameEn ? `<p style="font-family:monospace;direction:ltr;text-align:right;font-size:0.8rem;color:var(--text-muted);">${escapeHtml(nameEn)}</p>` : ''}
                    </div>
                    <div>
                        <p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.15rem;">آدرس</p>
                        <a href="${escapeHtml(url)}" target="_blank" rel="noopener noreferrer" style="font-family:monospace;direction:ltr;text-align:right;font-size:0.82rem;word-break:break-all;">${escapeHtml(url)}</a>
                    </div>
                    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
                        ${protocol ? `<span class="badge protocol-badge">${escapeHtml(protocol.toUpperCase())}</span>` : ''}
                        <span class="badge badge-primary">${escapeHtml(category)}</span>
                    </div>
                    ${description ? `<div><p style="font-size:0.78rem;color:var(--text-muted);margin-bottom:0.15rem;">توضیحات</p><p style="font-size:0.88rem;line-height:1.7;">${escapeHtml(description)}</p></div>` : ''}
                    <form id="review-form-${id}" method="POST" action="?id=${id}" style="display:flex;flex-direction:column;gap:0.7rem;margin-top:0.4rem;">
                        <input type="hidden" name="csrf_token" value="${escapeHtml(csrf)}">
                        <label class="review-toggle">
                            <input type="checkbox" name="reviewed" value="1" ${isReviewed ? 'checked' : ''}>
                            <span>بررسی شد</span>
                        </label>
                        <div class="form-group" style="margin-bottom:0;">
                            <label for="note-${id}" style="font-size:0.85rem;">یادداشت مدیر</label>
                            <textarea id="note-${id}" name="admin_note" rows="3" maxlength="500" placeholder="یادداشت اختیاری...">${escapeHtml(note)}</textarea>
                        </div>
                    </form>
                </div>
            `;

            const overlay = Modal.open({
                title: 'رسیدگی به درخواست',
                html,
                type: isReviewed ? 'success' : 'info',
                wide: true,
                confirmText: isReviewed ? 'ذخیره یادداشت' : 'علامت‌گذاری بررسی‌شده',
                cancelText: 'بستن',
                onConfirm: (ov) => {
                    const form = ov.querySelector(`#review-form-${id}`);
                    if (!form) return false;

                    const checkbox = form.querySelector('input[name="reviewed"]');
                    const wasReviewed = checkbox.checked;

                    // Append the action hidden input based on checkbox state
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'req_action';
                    actionInput.value = wasReviewed ? 'mark_reviewed' : 'mark_pending';
                    form.appendChild(actionInput);

                    form.submit();
                    return false;
                },
            });

            // Custom footer buttons if user wants to create mirror
            const footer = overlay.querySelector('.mh-modal-actions');
            if (isReviewed && !mirrorId) {
                const createBtn = document.createElement('a');
                createBtn.className = 'btn btn-success';
                createBtn.style.marginLeft = 'auto';
                createBtn.href = `${window.MH_SITE_URL || ''}/admin/mirrors.php?action=add&from_request=${id}`;
                createBtn.textContent = 'ساخت میرور از این درخواست';
                footer.insertBefore(createBtn, footer.firstChild);
            } else if (mirrorId) {
                const viewBtn = document.createElement('a');
                viewBtn.className = 'btn btn-success';
                viewBtn.style.marginLeft = 'auto';
                viewBtn.href = `${window.MH_SITE_URL || ''}/admin/mirrors.php?action=edit&id=${mirrorId}`;
                viewBtn.textContent = 'مشاهده میرور ساخته‌شده';
                footer.insertBefore(viewBtn, footer.firstChild);
            }
        });
    });

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }
});