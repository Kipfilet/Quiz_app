// Renders the #podium (top 3) and #leaderboard-list (the rest) containers
// from the real /api/leaderboard.php data. Shared by leaderboard.html and
// leaderboard_logout.html.

const podiumStyles = {
    1: {
        order: 'order-1 md:order-2',
        wrap: 'bg-white p-8 rounded-t-2xl rounded-b-xl shadow-lg border-t-8 border-2 border-amber-300 text-center relative',
        crown: '<div class="absolute -top-6 left-1/2 transform -translate-x-1/2 text-4xl">\u{1F451}</div>',
        badge: 'w-20 h-20 mx-auto bg-yellow-100 text-amber-500 rounded-full flex items-center justify-center text-3xl font-extrabold mb-4 mt-2',
        name: 'text-2xl font-extrabold text-gray-900 mb-1',
        score: 'text-amber-500 font-extrabold text-xl',
    },
    2: {
        order: 'order-2 md:order-1 transform md:translate-y-4',
        wrap: 'bg-white p-6 rounded-t-2xl rounded-b-xl shadow-md border-t-8 border-2 border-sky-300 text-center',
        crown: '',
        badge: 'w-16 h-16 mx-auto bg-sky-100 text-sky-600 rounded-full flex items-center justify-center text-2xl font-bold mb-4',
        name: 'text-xl font-bold text-gray-900 mb-1',
        score: 'text-sky-600 font-extrabold text-lg',
    },
    3: {
        order: 'order-3 md:order-3 transform md:translate-y-8',
        wrap: 'bg-white p-6 rounded-t-2xl rounded-b-xl shadow-md border-t-8 border-2 border-red-400 text-center',
        crown: '',
        badge: 'w-16 h-16 mx-auto bg-red-50 text-red-400 rounded-full flex items-center justify-center text-2xl font-bold mb-4',
        name: 'text-xl font-bold text-gray-900 mb-1',
        score: 'text-red-500 font-extrabold text-lg',
    },
};

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

function renderPodium(entries) {
    const podium = document.getElementById('podium');
    if (entries.length === 0) {
        podium.innerHTML = '<p class="text-gray-500 text-sm font-medium md:col-span-3 text-center">No players yet — be the first to set a score!</p>';
        return;
    }

    podium.innerHTML = entries.map((entry, i) => {
        const rank = i + 1;
        const style = podiumStyles[rank];
        return `
            <div class="${style.order} ${style.wrap}">
                ${style.crown}
                <div class="${style.badge}">${rank}</div>
                <h3 class="${style.name}">${escapeHtml(entry.username)}</h3>
                <p class="${style.score}">${entry.total_score.toLocaleString()} pts</p>
                <p class="text-gray-500 text-sm font-medium mt-2">${entry.quizzes_played} ${entry.quizzes_played === 1 ? 'Quiz' : 'Quizzes'} Played</p>
            </div>
        `;
    }).join('');
}

function renderList(entries) {
    const list = document.getElementById('leaderboard-list');
    if (entries.length === 0) {
        list.innerHTML = '<p class="px-6 py-4 text-gray-500 text-sm font-medium">Nobody else has played yet.</p>';
        return;
    }

    list.innerHTML = entries.map((entry, i) => `
        <div class="flex items-center justify-between px-6 py-4 hover:bg-yellow-50 transition-colors">
            <div class="flex items-center gap-4">
                <span class="text-lg font-bold text-gray-500 w-6 text-center">${i + 4}</span>
                <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center font-bold text-gray-600">
                    ${escapeHtml(entry.username.charAt(0).toUpperCase())}
                </div>
                <span class="font-bold text-gray-800 text-base sm:text-lg">${escapeHtml(entry.username)}</span>
            </div>
            <span class="font-extrabold text-sky-600">${entry.total_score.toLocaleString()} pts</span>
        </div>
    `).join('');
}

async function loadLeaderboard() {
    try {
        const { leaderboard } = await apiFetch('leaderboard.php?limit=10');
        renderPodium(leaderboard.slice(0, 3));
        renderList(leaderboard.slice(3));
    } catch (err) {
        document.getElementById('podium').innerHTML = '<p class="text-red-500 text-sm font-medium md:col-span-3 text-center">Could not load the leaderboard.</p>';
    }
}
