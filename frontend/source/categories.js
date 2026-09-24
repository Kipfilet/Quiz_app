// Builds the category picker on categories.html / categories_logout.html
// from the real category data in the DB. Shows category name + question
// count only (no questions or answers) — picking one starts that category's
// quiz via rules2.html?category=<slug> -> quiz.html?category=<slug>.

const categoryCardColors = {
    sky: { border: 'border-sky-300', badge: 'bg-sky-300 text-sky-700', button: 'bg-sky-600 hover:bg-sky-700 text-white' },
    red: { border: 'border-red-400', badge: 'bg-red-400 text-white', button: 'bg-red-500 hover:bg-red-600 text-white' },
    amber: { border: 'border-amber-300', badge: 'bg-amber-300 text-white', button: 'bg-amber-500 hover:bg-amber-600 text-white' },
};

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

async function loadCategoryPicker() {
    const container = document.getElementById('category-grid');

    try {
        const { categories } = await apiFetch('categories.php');
        const playable = categories.filter((c) => c.question_count > 0);

        if (playable.length === 0) {
            container.innerHTML = '<p class="text-gray-500 text-sm font-medium text-center col-span-full">No categories yet.</p>';
            return;
        }

        container.innerHTML = playable.map((cat) => {
            const colors = categoryCardColors[cat.color] || categoryCardColors.sky;
            return `
                <div class="bg-white rounded-xl shadow-sm hover:shadow-md transition-all duration-300 border-2 ${colors.border} p-6 flex flex-col">
                    <span class="inline-block ${colors.badge} text-xs px-2 py-1 rounded-full font-bold mb-3 w-fit">${cat.emoji || ''} Category</span>
                    <h3 class="text-lg font-bold text-gray-800 mb-1">${escapeHtml(cat.name)}</h3>
                    <p class="text-gray-500 text-sm font-medium mb-4">${cat.question_count} Question${cat.question_count === 1 ? '' : 's'}</p>
                    <a href="rules2.html?category=${encodeURIComponent(cat.slug)}"
                        class="mt-auto w-full text-center font-bold py-2 px-4 rounded-lg transition-colors duration-200 ${colors.button}">
                        Play this category
                    </a>
                </div>
            `;
        }).join('');
    } catch (err) {
        container.innerHTML = '<p class="text-red-500 text-sm font-medium text-center col-span-full">Could not load categories.</p>';
    }
}
