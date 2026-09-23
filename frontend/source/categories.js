// Builds the "Question Database" accordion on categories.html /
// categories_logout.html from the real category + question data in the DB.

const accordionColors = {
    sky: { border: 'border-sky-300', text: 'text-sky-700', bg: 'bg-sky-50', hover: 'hover:bg-sky-100', answer: 'text-sky-600' },
    red: { border: 'border-red-400', text: 'text-red-500', bg: 'bg-red-50', hover: 'hover:bg-red-100', answer: 'text-red-500' },
    amber: { border: 'border-amber-300', text: 'text-amber-600', bg: 'bg-yellow-100', hover: 'hover:bg-yellow-200', answer: 'text-amber-600' },
};

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

async function loadCategoryAccordion() {
    const container = document.getElementById('accordion-container');

    try {
        const [{ categories }, { questions }] = await Promise.all([
            apiFetch('categories.php'),
            apiFetch('questions.php'),
        ]);

        const byCategory = new Map();
        for (const q of questions) {
            if (!q.category) continue;
            if (!byCategory.has(q.category.id)) byCategory.set(q.category.id, []);
            byCategory.get(q.category.id).push(q);
        }

        const populated = categories.filter((c) => byCategory.has(c.id));

        if (populated.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-sm font-medium text-center">No questions yet.</p>';
            return;
        }

        container.innerHTML = populated.map((cat) => {
            const colors = accordionColors[cat.color] || accordionColors.sky;
            const catQuestions = byCategory.get(cat.id);

            const questionsHtml = catQuestions.map((q, i) => `
                <div>
                    <p class="font-bold text-gray-800 mb-2">${i + 1}. ${escapeHtml(q.question)}</p>
                    <ul class="text-gray-600 pl-4 space-y-1">
                        ${['A', 'B', 'C', 'D'].map((letter) => {
                            const isCorrect = letter === q.answer;
                            const cls = isCorrect ? `font-bold ${colors.answer}` : '';
                            return `<li${cls ? ` class="${cls}"` : ''}>${letter}) ${escapeHtml(q.options[letter])}${isCorrect ? ' ✓' : ''}</li>`;
                        }).join('')}
                    </ul>
                </div>
            `).join('');

            return `
                <div class="bg-white rounded-xl shadow-sm border-2 ${colors.border} overflow-hidden">
                    <button
                        class="w-full text-left px-6 py-4 font-bold text-xl ${colors.text} ${colors.bg} ${colors.hover} transition-colors flex justify-between items-center focus:outline-none accordion-btn">
                        <span>${cat.emoji || ''} ${escapeHtml(cat.name)} (${catQuestions.length} Question${catQuestions.length === 1 ? '' : 's'})</span>
                        <svg class="h-6 w-6 transform transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <div class="hidden px-6 py-4 bg-white border-t border-sky-100 max-h-96 overflow-y-auto space-y-6 accordion-content">
                        ${questionsHtml}
                    </div>
                </div>
            `;
        }).join('');

        document.querySelectorAll('.accordion-btn').forEach((acc) => {
            acc.addEventListener('click', function () {
                const content = this.nextElementSibling;
                const icon = this.querySelector('svg');
                content.classList.toggle('hidden');
                icon.classList.toggle('rotate-180');
            });
        });
    } catch (err) {
        container.innerHTML = '<p class="text-red-500 text-sm font-medium text-center">Could not load the question database.</p>';
    }
}
