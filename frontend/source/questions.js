let questionText = document.getElementById('questionText');
let isOver = false;
let difficulty;
let questionOptions = [
    document.getElementById('option1'),
    document.getElementById('option2'),
    document.getElementById('option3'),
    document.getElementById('option4')
];
function setDifficulty(difficultyOption){
    difficulty = difficultyOption;
    document.getElementById("difficultySelection").innerHTML = "";
    fetchQuestions(difficulty)
}

// Removes then re-adds an animation class so it replays even if the
// element already had it (CSS animations don't restart on a no-op add).
function retriggerAnimation(el, className) {
    if (!el) return;
    el.classList.remove(className);
    void el.offsetWidth; // force a reflow so the browser notices the removal
    el.classList.add(className);
}
let score = 0;
let questionArray = [];
let questionMaxIndex = 0;
let randomQuestionIndex = 0;
let scoreDisplay = document.getElementById('score');
let correctAnswers = 0
const quizParams = new URLSearchParams(window.location.search);
const quizCategory = quizParams.get('category');

function fetchQuestions(diff){
    const path = quizCategory ? `questions.php?category=${encodeURIComponent(quizCategory)}` : 'questions.php';
    apiFetch(path)
        .then(data => {
            questionArray = []
            const questions = data.questions;
            console.log('Questions loaded:', questions);
            for (const question of questions) {
                if (question.difficulty = diff || diff=="all"){
                    questionMaxIndex++;
                    questionArray.push(question);
                }
            }
            loadQuestions()
        }).catch(err => console.error('Error loading questions: ', err));

}
    
function loadQuestions() {
            randomQuestionIndex = Math.floor(Math.random() * questionMaxIndex);
            let randomQuestion = questionArray[randomQuestionIndex];
            console.log(randomQuestion)
            questionText.textContent = randomQuestion.question;
            option1.textContent = randomQuestion.options.A;
            option2.textContent = randomQuestion.options.B;
            option3.textContent = randomQuestion.options.C;
            option4.textContent = randomQuestion.options.D;
            retriggerAnimation(document.getElementById('questionBlock'), 'animate-fade-in');
            countdownNextQuestion(10, 'Time left: ',"timer");
    }

async function checkAnswer(selectedOption, questionId) {
    if (selectedOption == questionArray[randomQuestionIndex].answer) {
        questionId.classList.remove('bg-sky-600', 'hover:bg-sky-700', 'active:bg-sky-800');
        questionId.classList.add('bg-green-500');
        retriggerAnimation(questionId, 'animate-pop');
        if (questionArray[randomQuestionIndex].difficulty === 'easy') {
            score += 10;
        }
        else if (questionArray[randomQuestionIndex].difficulty === 'normal') {
            score += 15;
        }
        else if (questionArray[randomQuestionIndex].difficulty === 'hard') {
            score += 20;
        }
        scoreDisplay.textContent = score;
        retriggerAnimation(scoreDisplay, 'animate-pop');
        correctAnswers++;
        disableButtons();
        showCorrectAnswer();
        await countdownNextQuestion(3, 'Next question in: ',"cooldown");
    } else {
        questionId.classList.remove('bg-sky-600', 'hover:bg-sky-700', 'active:bg-sky-800');
        questionId.classList.add('bg-red-500');
        retriggerAnimation(questionId, 'animate-shake');
        disableButtons();
        showCorrectAnswer();
        removeHeart();
        if(!isOver){
            await countdownNextQuestion(3, 'Next question in: ',"cooldown");
        }
    }
}
function disableButtons() {
    for (let option of questionOptions) {
        option.disabled = true;
    }
}
function enableButtons() {
    for (let option of questionOptions) {
        option.disabled = false;
    }
}
function resetButtonColors() {
    for (let option of questionOptions) {
        option.classList.remove('bg-green-500', 'bg-red-500');
        option.classList.add('bg-sky-600', 'hover:bg-sky-700', 'active:bg-sky-800');
    }
}
function showCorrectAnswer() {
    let correctAnswer = questionArray[randomQuestionIndex].answer;
    for (let option of questionOptions) {
        if (option.textContent === questionArray[randomQuestionIndex].options[correctAnswer]) {
            option.classList.remove('bg-sky-600', 'hover:bg-sky-700', 'active:bg-sky-800');
            option.classList.add('bg-green-500');
        }
    }
}
let countdownUse = false;
let countdownInterval;
function countdownNextQuestion(countdownDuration, countdownExtraText, countdownType) {
    let timerText = document.getElementById('timerText');
    timerText.textContent = countdownExtraText;
    let timer = document.getElementById('timer');
    let timeLeft = countdownDuration;
    timer.textContent = timeLeft;
    timer.classList.remove('timer-urgent');
    if (countdownUse) {
        clearInterval(countdownInterval);
    }
    countdownUse = true;
    {
        countdownInterval = setInterval(() => {
        timeLeft--;
        timer.textContent = timeLeft;
        console.log('Countdown: ', timeLeft);
        if (countdownType === 'timer' && timeLeft > 0 && timeLeft <= 3) {
            timer.classList.add('timer-urgent');
        } else {
            timer.classList.remove('timer-urgent');
        }
        if (timeLeft <= 0) {
            if(countdownType == "timer"){
                removeHeart();
            }
            countdownUse = false;
            clearInterval(countdownInterval);
            resetButtonColors();
            if(!isOver){
                loadQuestions();
                enableButtons();
            }
        }
    }, 1000);
    }
}
let heartCount = 3
function removeHeart() {
    retriggerAnimation(heartContainer, 'animate-shake');
    if (heartCount > 1){
        heartContainer.children[heartCount - 1].ariaCurrent = "false";
        heartContainer.children[heartCount - 2].ariaCurrent = "true";
        heartCount--
    }
    else if(!isOver){
        heartContainer.children[heartCount - 1].ariaCurrent="false";
        heartCount--;
        gameOver()
    }
}
function gameOver() {
    isOver = true
    disableButtons()
    document.getElementById("endScreen").classList.remove("invisible");
    let totalScore = score;
    let totalScoreContainer = document.getElementById("totalScore");
    totalScoreContainer.textContent = totalScore;
    let totalHearsUsed = 3 - heartCount;
    let totalHeartContainer = document.getElementById("totalHearts");
    totalHeartContainer.innerText = totalHearsUsed;
    let totalQuestions = document.getElementById("totalQuestions");
    totalQuestions.innerHTML= correctAnswers;

    saveQuizAttempt(totalScore, correctAnswers, totalHearsUsed);
}

async function saveQuizAttempt(totalScore, correctAnswers, heartsUsed) {
    const saveStatus = document.getElementById('saveStatus');
    const returnHomeBtn = document.getElementById('returnHomeBtn');

    const user = await getCurrentUser();
    returnHomeBtn.onclick = () => {
        window.location.href = user ? 'home-logedin.html' : 'home.html';
    };

    try {
        await apiFetch('quiz_attempts.php', {
            method: 'POST',
            body: JSON.stringify({
                score: totalScore,
                correct_answers: correctAnswers,
                hearts_used: heartsUsed,
                category_slug: quizCategory,
            }),
        });
        saveStatus.textContent = user
            ? 'Your score has been saved to the leaderboard!'
            : 'Sign in to save your score to the leaderboard next time.';
    } catch (err) {
        saveStatus.textContent = 'Could not save your score.';
    }
}
