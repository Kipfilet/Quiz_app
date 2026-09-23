// Powers quiz-create.html: loads the real category list, lets the user add
// and remove question blocks, and publishes the finished quiz to the DB via
// POST /api/quizzes.php.

let questionCount = 0;

function questionBlockHtml(n) {
    return `
        <div class="question-block border-t-2 border-yellow-100 pt-6 first:border-t-0 first:pt-0" data-question-index="${n}">
            <div class="flex justify-between items-center mb-4">
                <span class="text-sm font-bold text-gray-500 bg-yellow-100 px-3 py-1 rounded-full">Question ${n}</span>
                <button type="button" class="remove-question-btn text-red-500 hover:text-red-700 text-sm font-bold">Remove</button>
            </div>
            <div class="space-y-6">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Question Text</label>
                    <input
                        class="question-text shadow-sm appearance-none border-2 border-sky-300 rounded-lg w-full py-2 sm:py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-0 focus:border-amber-300 transition-all duration-200"
                        type="text" placeholder="Type your question here...">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-3">Answers (Select the correct one)</label>
                    <div class="space-y-3">
                        ${['A', 'B', 'C', 'D'].map((letter) => `
                            <div class="flex items-center gap-3 bg-yellow-100/30 p-2 sm:p-3 rounded-lg border-2 border-transparent focus-within:border-amber-300 transition-colors">
                                <input type="radio" name="q${n}-correct" value="${letter}"
                                    class="option-correct w-5 h-5 text-sky-600 focus:ring-sky-600 cursor-pointer" ${letter === 'A' ? 'checked' : ''}>
                                <input
                                    class="option-text appearance-none bg-transparent border-b-2 border-gray-300 w-full py-1 px-2 text-gray-700 focus:outline-none focus:border-sky-600 transition-all"
                                    data-letter="${letter}" type="text" placeholder="Option ${letter}${letter === 'A' ? ' (Correct Answer)' : ''}">
                            </div>
                        `).join('')}
                    </div>
                </div>
            </div>
        </div>
    `;
}

function addQuestionBlock() {
    questionCount++;
    const container = document.getElementById('questions-container');
    container.insertAdjacentHTML('beforeend', questionBlockHtml(questionCount));
    updateRemoveButtons();
}

function updateRemoveButtons() {
    const blocks = document.querySelectorAll('.question-block');
    document.querySelectorAll('.remove-question-btn').forEach((btn) => {
        btn.classList.toggle('hidden', blocks.length <= 1);
    });
}

function collectQuestions() {
    return Array.from(document.querySelectorAll('.question-block')).map((block) => {
        const options = {};
        block.querySelectorAll('.option-text').forEach((input) => {
            options[input.dataset.letter] = input.value.trim();
        });
        const correct = block.querySelector('.option-correct:checked');
        return {
            question: block.querySelector('.question-text').value.trim(),
            options,
            answer: correct ? correct.value : '',
        };
    });
}

async function populateCategories() {
    const select = document.getElementById('quiz-category');
    try {
        const { categories } = await apiFetch('categories.php');
        select.innerHTML = '<option value="">No category (general trivia)</option>' +
            categories.map((c) => `<option value="${c.slug}">${c.emoji || ''} ${c.name}</option>`).join('');
    } catch (err) {
        select.innerHTML = '<option value="">No category (general trivia)</option>';
    }
}

function initQuizCreate() {
    populateCategories();
    addQuestionBlock();

    document.getElementById('add-question-btn').addEventListener('click', addQuestionBlock);

    document.getElementById('questions-container').addEventListener('click', (e) => {
        if (e.target.closest('.remove-question-btn')) {
            e.target.closest('.question-block').remove();
            updateRemoveButtons();
        }
    });

    const form = document.getElementById('create-quiz-form');
    const formError = document.getElementById('form-error');
    const publishBtn = document.getElementById('publish-btn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        formError.classList.add('hidden');

        publishBtn.disabled = true;
        publishBtn.textContent = 'Publishing...';

        try {
            await apiFetch('quizzes.php', {
                method: 'POST',
                body: JSON.stringify({
                    title: document.getElementById('quiz-title').value.trim(),
                    description: document.getElementById('quiz-description').value.trim(),
                    category_slug: document.getElementById('quiz-category').value || null,
                    difficulty: document.getElementById('quiz-difficulty').value,
                    questions: collectQuestions(),
                }),
            });
            window.location.href = 'home-logedin.html';
        } catch (err) {
            formError.textContent = err.message || 'Could not publish this quiz.';
            formError.classList.remove('hidden');
            publishBtn.disabled = false;
            publishBtn.textContent = 'Publish Quiz';
        }
    });
}
