/**
 * Progressive enhancement only: every page works without JavaScript, and these
 * listeners just remove a click for the interactions that get tedious.
 */

// Listing gallery: clicking a thumbnail swaps the large photo.
document.addEventListener('click', (event) => {
    const thumbnail = event.target.closest('[data-gallery-thumb]');

    if (!thumbnail) {
        return;
    }

    event.preventDefault();

    const gallery = thumbnail.closest('[data-gallery]');
    const target = thumbnail.dataset.galleryTarget;

    if (!gallery || !target) {
        return;
    }

    gallery.querySelectorAll('[data-gallery-image]').forEach((image) => {
        image.hidden = image.id !== target;
    });

    gallery.querySelectorAll('[data-gallery-thumb]').forEach((button) => {
        button.setAttribute('aria-current', button === thumbnail ? 'true' : 'false');
        button.classList.toggle('ring-brand-600', button === thumbnail);
        button.classList.toggle('ring-transparent', button !== thumbnail);
    });
});

// Sidebar filters are a GET form, so submitting applies them; this keeps the
// facet list from jumping when a checkbox is toggled and the user would rather
// pick several before applying.
document.querySelectorAll('[data-autosubmit]').forEach((input) => {
    input.addEventListener('change', () => input.form?.requestSubmit());
});

// SPARQL editor: Ctrl/Cmd + Enter runs the query without reaching for the mouse.
document.querySelectorAll('textarea[name="query"]').forEach((editor) => {
    editor.addEventListener('keydown', (event) => {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            editor.form?.requestSubmit();
        }
    });
});

const chatbot = document.querySelector('[data-chatbot]');
if (chatbot) {
    const panel = chatbot.querySelector('[data-chatbot-panel]');
    const toggle = chatbot.querySelector('[data-chatbot-toggle]');
    const close = chatbot.querySelector('[data-chatbot-close]');
    const form = chatbot.querySelector('[data-chatbot-form]');
    const input = chatbot.querySelector('[data-chatbot-input]');
    const send = chatbot.querySelector('[data-chatbot-send]');
    const messages = chatbot.querySelector('[data-chatbot-messages]');
    const suggestions = chatbot.querySelector('[data-chatbot-suggestions]');
    const placeholder = chatbot.dataset.placeholder || '';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const scrollToBottom = () => {
        requestAnimationFrame(() => {
            messages.scrollTop = messages.scrollHeight;
        });
    };

    const setOpen = (open) => {
        panel.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');

        if (open) {
            input.focus();
            scrollToBottom();
        }
    };

    const appendMessage = (role, text) => {
        const bubble = document.createElement('div');
        bubble.className = role === 'user'
            ? 'ml-auto max-w-[85%] rounded-2xl rounded-br-sm bg-brand-600 px-3.5 py-2.5 text-sm text-white'
            : 'max-w-[85%] rounded-2xl rounded-bl-sm bg-slate-100 px-3.5 py-2.5 text-sm leading-relaxed text-slate-700';
        bubble.textContent = text;

        messages.append(bubble);
        scrollToBottom();

        return bubble;
    };

    const appendCars = (cars) => {
        const list = document.createElement('ul');
        list.className = 'space-y-2';

        cars.forEach((car) => {
            const item = document.createElement('li');
            const link = document.createElement('a');
            link.href = car.url;
            link.className = 'flex gap-3 rounded-xl border border-slate-200 bg-white p-2 transition hover:border-brand-300 hover:bg-brand-50';

            const image = document.createElement('img');
            image.src = car.image || placeholder;
            image.alt = '';
            image.loading = 'lazy';
            image.className = 'h-16 w-20 shrink-0 rounded-lg bg-slate-100 object-cover';
            image.addEventListener('load', () => scrollToBottom());
            image.onerror = () => {
                image.src = placeholder;
            };

            const body = document.createElement('div');
            body.className = 'min-w-0';

            const title = document.createElement('p');
            title.className = 'truncate text-sm font-semibold text-slate-900';
            title.textContent = car.label;

            const summary = document.createElement('p');
            summary.className = 'mt-0.5 text-xs text-slate-500';
            summary.textContent = car.summary;

            const price = document.createElement('p');
            price.className = 'mt-1 text-sm font-bold text-brand-700';
            price.textContent = car.price;

            body.append(title, summary, price);
            link.append(image, body);
            item.append(link);
            list.append(item);
        });

        messages.append(list);
        scrollToBottom();
    };

    const appendLink = (url, label) => {
        const link = document.createElement('a');
        link.href = url;
        link.textContent = label;
        link.className = 'inline-flex text-sm font-semibold text-brand-700 hover:underline';

        messages.append(link);
        scrollToBottom();
    };

    const renderSuggestions = (items) => {
        if (!Array.isArray(items)) {
            return;
        }

        suggestions.replaceChildren();

        items.slice(0, 5).forEach((text) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.dataset.chatbotSuggestion = text;
            button.className = 'chip-brand transition hover:bg-brand-100';
            button.textContent = text;
            suggestions.append(button);
        });
    };

    const ask = async (message) => {
        const text = message.trim();

        if (text === '') {
            return;
        }

        appendMessage('user', text);
        input.value = '';
        send.disabled = true;
        messages.setAttribute('aria-busy', 'true');

        const pending = appendMessage('bot', 'Mencari di katalog…');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    message: text,
                    context: {
                        page: `${window.location.pathname}${window.location.search}`,
                        car: chatbot.dataset.chatbotCar || '',
                    },
                }),
            });

            if (!response.ok) {
                throw new Error('Asisten tidak tersedia.');
            }

            const data = await response.json();
            pending.remove();

            appendMessage('bot', data.reply || 'Maaf, saya belum bisa menjawab itu.');
            appendCars(Array.isArray(data.cars) ? data.cars : []);

            if (data.url) {
                appendLink(data.url, 'Lihat semua di katalog');
            }

            renderSuggestions(data.suggestions);
        } catch (error) {
            pending.remove();
            appendMessage('bot', error.message || 'Asisten sedang tidak bisa dihubungi. Coba lagi sebentar.');
        } finally {
            send.disabled = false;
            messages.removeAttribute('aria-busy');
            input.focus();
        }
    };

    toggle.addEventListener('click', () => setOpen(panel.hidden));

    close.addEventListener('click', () => {
        setOpen(false);
        toggle.focus();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            setOpen(false);
            toggle.focus();
        }
    });

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        ask(input.value);
    });

    suggestions.addEventListener('click', (event) => {
        const button = event.target.closest('[data-chatbot-suggestion]');

        if (!button) {
            return;
        }

        ask(button.dataset.chatbotSuggestion || button.textContent || '');
    });
}
