/**
 * Admin area JavaScript — vanilla JS, no build step.
 * Handles sidebar toggle, destructive-action confirmation, rich text
 * editor initialisation, image previews, and drag-to-reorder lists.
 */
(function () {
    'use strict';

    // ---------------------------------------------------------------
    // Sidebar toggle (mobile/tablet)
    // ---------------------------------------------------------------
    var sidebarToggle = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('adminSidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('is-open');
        });
        document.addEventListener('click', function (e) {
            if (window.innerWidth <= 900 && sidebar.classList.contains('is-open')
                && !sidebar.contains(e.target) && e.target !== sidebarToggle) {
                sidebar.classList.remove('is-open');
            }
        });
    }

    // ---------------------------------------------------------------
    // Confirm before destructive actions (delete buttons/forms)
    // ---------------------------------------------------------------
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            var message = el.getAttribute('data-confirm') || 'Are you sure?';
            if (!window.confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // ---------------------------------------------------------------
    // Image upload preview
    // ---------------------------------------------------------------
    document.querySelectorAll('[data-preview-target]').forEach(function (input) {
        input.addEventListener('change', function () {
            var target = document.querySelector(input.getAttribute('data-preview-target'));
            if (!target || !input.files || !input.files[0]) return;
            var reader = new FileReader();
            reader.onload = function (e) { target.src = e.target.result; target.hidden = false; };
            reader.readAsDataURL(input.files[0]);
        });
    });

    // ---------------------------------------------------------------
    // Rich text editor (Quill, vendored locally — see assets/vendor/quill)
    // Any <div data-editor-for="fieldId"> becomes a Quill editor that
    // writes its HTML into the hidden textarea with id=fieldId on submit.
    // ---------------------------------------------------------------
    document.querySelectorAll('[data-editor-for]').forEach(function (container) {
        var targetId = container.getAttribute('data-editor-for');
        var textarea = document.getElementById(targetId);
        if (!textarea || typeof Quill === 'undefined') return;

        textarea.style.display = 'none';
        var editorDiv = document.createElement('div');
        container.appendChild(editorDiv);

        var quill = new Quill(editorDiv, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ header: [2, 3, 4, false] }],
                    ['bold', 'italic'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link', 'image', 'blockquote'],
                    ['clean'],
                ],
            },
        });

        quill.root.innerHTML = textarea.value;

        var form = textarea.closest('form');
        if (form) {
            form.addEventListener('submit', function () {
                textarea.value = quill.root.innerHTML;
            });
        }
    });

    // ---------------------------------------------------------------
    // Drag-to-reorder lists (gallery items, committee members).
    // Uses the native HTML5 drag-and-drop API — no external library.
    // Posts the new order to the given endpoint via fetch on drop.
    // ---------------------------------------------------------------
    document.querySelectorAll('[data-reorder-url]').forEach(function (list) {
        var url = list.getAttribute('data-reorder-url');
        var dragged = null;

        list.querySelectorAll('[data-reorder-id]').forEach(function (item) {
            item.setAttribute('draggable', 'true');

            item.addEventListener('dragstart', function () {
                dragged = item;
                item.classList.add('sortable-ghost');
            });
            item.addEventListener('dragend', function () {
                item.classList.remove('sortable-ghost');
                sendNewOrder();
            });
            item.addEventListener('dragover', function (e) {
                e.preventDefault();
                var after = getDragAfterElement(list, e.clientY);
                if (!dragged) return;
                if (after == null) {
                    list.appendChild(dragged);
                } else {
                    list.insertBefore(dragged, after);
                }
            });
        });

        function getDragAfterElement(container, y) {
            var items = Array.prototype.slice.call(container.querySelectorAll('[data-reorder-id]:not(.sortable-ghost)'));
            return items.reduce(function (closest, child) {
                var box = child.getBoundingClientRect();
                var offset = y - box.top - box.height / 2;
                if (offset < 0 && offset > closest.offset) {
                    return { offset: offset, element: child };
                }
                return closest;
            }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
        }

        function sendNewOrder() {
            var ids = Array.prototype.slice.call(list.querySelectorAll('[data-reorder-id]'))
                .map(function (el) { return el.getAttribute('data-reorder-id'); });
            var csrf = document.querySelector('meta[name="csrf-token"]');
            fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order: ids, csrf_token: csrf ? csrf.content : '' }),
            }).catch(function () {
                window.alert('Could not save the new order. Please check your connection and try again.');
            });
        }
    });
})();
