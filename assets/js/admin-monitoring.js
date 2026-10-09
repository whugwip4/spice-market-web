(() => {
    // Каждые 30 секунд страница мониторинга запрашивает, сколько посетителей сейчас на сайте
    const fields = document.querySelectorAll('[data-live]');

    if (!fields.length) {
        return;
    }

    const number = new Intl.NumberFormat('ru-RU');

    const refresh = async () => {
        try {
            const response = await fetch('/admin/api/monitor.php', { credentials: 'same-origin', cache: 'no-store' });
            if (!response.ok) {
                return;
            }

            const data = await response.json();
            fields.forEach((field) => {
                const value = data[field.dataset.live];
                if (value !== undefined && value !== null) {
                    field.textContent = typeof value === 'number' ? number.format(value) : value;
                }
            });
        } catch (error) {
            // при обрыве связи остаются последние полученные значения
        }
    };

    setInterval(refresh, 30000);
})();
