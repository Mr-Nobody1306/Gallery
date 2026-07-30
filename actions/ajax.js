document.addEventListener('DOMContentLoaded', () => {
    initBurgerMenu();
    initThemeToggle();
    initPageForms();
    initSettingsDialog();
    setGreeting();
    initLikeButtons();
});

function initBurgerMenu() {
    const burgerBtn = document.querySelector('.burger-btn');
    const menu = document.querySelector('.menu');
    if (!burgerBtn || !menu) return;

    burgerBtn.addEventListener('click', () => {
        menu.classList.toggle('active');
        burgerBtn.classList.toggle('active');
    });
}

function initThemeToggle() {
    if (document.getElementById('themeToggle')) return;
    const toggle = document.createElement('button');
    toggle.id = 'themeToggle';
    toggle.type = 'button';
    toggle.className = 'theme-toggle';
    toggle.setAttribute('aria-label', 'Toggle theme');
    toggle.addEventListener('click', toggleDarkMode);
    document.body.prepend(toggle);
    applyTheme();
}

function applyTheme(theme) {
    const stored = localStorage.getItem('galleryTheme');
    const preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    const themeChoice = theme || stored || preferred;
    document.documentElement.classList.toggle('dark-mode', themeChoice === 'dark');
    localStorage.setItem('galleryTheme', themeChoice);
    const toggle = document.getElementById('themeToggle');
    if (toggle) {
        toggle.innerHTML = themeChoice === 'dark'
            ? '<img src="sys_media/light-mode.png" alt="Switch to light mode">'
            : '<img src="sys_media/night-mode.png" alt="Switch to dark mode">';
        
    }
}

function toggleDarkMode() {
    const active = document.documentElement.classList.contains('dark-mode');
    applyTheme(active ? 'light' : 'dark');
}

function setGreeting() {
    const greeting = document.getElementById('greeting');
    if (!greeting) return;
    const hour = new Date().getHours();
    greeting.innerText = hour < 18 ? 'Bonjour' : 'Bonsoir';
}

function ajaxHandler(action, data) {
    data.append('action', action);
    return fetch('actions/ajax_handler.php', {
        method: 'POST',
        body: data,
        credentials: 'include'
    })
        .then((response) => response.json());
}

function initPageForms() {
    const loginForm = document.getElementById('loginForm');
    const signupForm = document.getElementById('signupForm');
    const searchForm = document.getElementById('searchform');
    const addPhotoForm = document.getElementById('addPhotoForm');
    const settingsForm = document.getElementById('settingsForm');

    if (loginForm) {
        loginForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const resultContainer = document.getElementById('formError');
            clearMessage(resultContainer);

            const formData = new FormData(loginForm);
            const result = await ajaxHandler('login', formData);
            if (result.success) {
                window.location.href = 'index.php';
            } else {
                showMessage(resultContainer, result.message || 'Login failed');
            }
        });
    }

    if (signupForm) {
        signupForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const resultContainer = document.getElementById('formError');
            clearMessage(resultContainer);

            const formData = new FormData(signupForm);
            const result = await ajaxHandler('signup', formData);
            if (result.success) {
                showMessage(resultContainer, result.message || 'Signed up successfully', true);
                setTimeout(() => {
                    window.location.href = 'login.html';
                }, 1200);
            } else {
                showMessage(resultContainer, result.message || 'Sign up failed');
            }
        });
    }

    if (searchForm) {
        searchForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const formData = new FormData(searchForm);
            const result = await ajaxHandler('search', formData);
            renderSearchResults(result);
        });
    }

    if (addPhotoForm) {
        addPhotoForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const resultContainer = document.getElementById('addPhotoMessage');
            clearMessage(resultContainer);

            const formData = new FormData(addPhotoForm);
            const result = await ajaxHandler('add_photo', formData);
            if (result.success) {
                showMessage(resultContainer, result.message || 'Photo added successfully', true);
                addPhotoForm.reset();
                setTimeout(() => location.reload(), 1200);
            } else {
                showMessage(resultContainer, result.message || 'Failed to add photo');
            }
        });
    }

    if (settingsForm) {
        settingsForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const resultContainer = document.getElementById('settingsMessage');
            clearMessage(resultContainer);

            const formData = new FormData(settingsForm);
            const result = await ajaxHandler('update_settings', formData);
            if (result.success) {
                showMessage(resultContainer, result.message || 'Settings saved', true);
            } else {
                showMessage(resultContainer, result.message || 'Unable to save settings');
            }
        });
    }
}

function initSettingsDialog() {
    const settingsToggle = document.getElementById('settingsToggle');
    const settingsModal = document.getElementById('settingsModal');
    const settingsClose = document.getElementById('settingsClose');
    const deleteAccountBtn = document.getElementById('deleteAccountBtn');

    if (settingsToggle && settingsModal) {
        settingsToggle.addEventListener('click', (event) => {
            event.preventDefault();
            settingsModal.classList.remove('hidden');
            document.body.classList.add('modal-open');
        });
    }

    if (settingsClose && settingsModal) {
        settingsClose.addEventListener('click', () => {
            settingsModal.classList.add('hidden');
            document.body.classList.remove('modal-open');
        });
    }

    if (settingsModal) {
        settingsModal.addEventListener('click', (event) => {
            if (event.target === settingsModal) {
                settingsModal.classList.add('hidden');
                document.body.classList.remove('modal-open');
            }
        });
    }

    if (deleteAccountBtn) {
        deleteAccountBtn.addEventListener('click', async () => {
            if (!confirm('Delete your account and all gallery data? This cannot be undone.')) return;

            const result = await ajaxHandler('delete_account', new FormData());
            if (!result.success) {
                alert(result.message || 'Unable to delete account');
                return;
            }

            window.location.href = 'login.html';
        });
    }
}

function renderSearchResults(result) {
    const container = document.getElementById('searchResults');
    const message = document.getElementById('searchMessage');
    if (!container) return;
    container.innerHTML = '';
    if (!result.success) {
        if (message) showMessage(message, result.message || 'Search failed');
        return;
    }

    if (!result.results || result.results.length === 0) {
        container.innerHTML = '<div class="search-empty">No media found for that query.</div>';
        return;
    }

    container.innerHTML = result.results
        .map((photo) => renderPhotoCard(photo))
        .join('');
}

function isVideoUrl(url) {
    return /\.(mp4|webm|ogg|mov|m4v)(\?|$)/i.test(url);
}

function renderMediaPreview(src, title) {
    if (isVideoUrl(src)) {
        return `
            <video controls preload="metadata" title="${escapeHtml(title)}">
                <source src="${src}" type="video/${src.split('.').pop().split('?')[0]}">
                Your browser does not support the video tag.
            </video>
        `;
    }
    return `<img src="${src}" alt="${escapeHtml(title)}">`;
}

function renderPhotoCard(photo) {
    return `
        <article class="photo-card" id="search-photo-${photo.id}">
            <div class="photo-frame">
                ${renderMediaPreview(photo.thumbnail, photo.title)}
            </div>
            <div class="photo-info">
                <div class="photo-title-row">
                    <h3>${escapeHtml(photo.title)}</h3>
                    <button class="like-btn ${photo.liked ? 'liked' : ''}" data-like-photo-id="${photo.id}">
                        ${photo.liked ? '♥' : '♡'} <span class="likes-count">${photo.likes_count}</span>
                    </button>
                </div>
                <p>${escapeHtml(photo.photo_description || 'No description')}</p>
                <div class="photo-meta-row">
                    <span>${escapeHtml(photo.album_title)}</span>
                    <span>By ${escapeHtml(photo.owner)}</span>
                    <time>${new Date(photo.uploaded_at).toLocaleDateString()}</time>
                </div>
            </div>
        </article>
    `;
}

function initLikeButtons() {
    document.body.addEventListener('click', async (event) => {
        const actionButton = event.target.closest('[data-photo-action]');
        if (actionButton) {
            event.preventDefault();
            const photoId = actionButton.dataset.photoId;
            const action = actionButton.dataset.photoAction;

            if (action === 'delete') {
                if (!confirm('Delete this photo permanently?')) return;
                const formData = new FormData();
                formData.append('photo_id', photoId);
                const result = await ajaxHandler('delete_photo', formData);
                if (!result.success) {
                    alert(result.message || 'Unable to delete photo');
                    return;
                }
                const card = actionButton.closest('.photo-card');
                if (card) card.remove();
                return;
            }

            if (action === 'edit') {
                const card = actionButton.closest('.photo-card');
                const currentTitle = card?.querySelector('h3')?.innerText || '';
                const currentDescription = card?.querySelector('p')?.innerText || '';
                const title = prompt('Photo title', currentTitle);
                if (title === null) return;
                const description = prompt('Photo description', currentDescription);
                if (description === null) return;

                const formData = new FormData();
                formData.append('photo_id', photoId);
                formData.append('title', title.trim());
                formData.append('photo_description', description.trim());
                const result = await ajaxHandler('update_photo', formData);
                if (!result.success) {
                    alert(result.message || 'Unable to update photo');
                    return;
                }
                if (card) {
                    const titleElement = card.querySelector('h3');
                    const descElement = card.querySelector('p');
                    if (titleElement) titleElement.innerText = title.trim();
                    if (descElement) descElement.innerText = description.trim() || 'No description provided.';
                }
                alert(result.message || 'Photo updated');
                return;
            }
        }

        const button = event.target.closest('[data-like-photo-id]');
        if (!button) return;
        event.preventDefault();

        const photoId = button.dataset.likePhotoId;
        const formData = new FormData();
        formData.append('photo_id', photoId);

        const result = await ajaxHandler('toggle_like', formData);
        if (!result.success) {
            alert(result.message || 'Unable to update like');
            return;
        }

        button.classList.toggle('liked', result.liked);
        button.innerHTML = `${result.liked ? '♥' : '♡'} <span class="likes-count">${result.likes_count}</span>`;
    });
}

function showMessage(container, text, success = false) {
    if (!container) {
        alert(text);
        return;
    }
    container.textContent = text;
    container.className = success ? 'form-message success' : 'form-message error';
}

function clearMessage(container) {
    if (!container) return;
    container.textContent = '';
    container.className = 'form-message';
}

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

