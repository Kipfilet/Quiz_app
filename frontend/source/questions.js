let questionText = document.getElementById('questionText');
let questionOptions = [
    document.getElementById('option1'),
    document.getElementById('option2'),
    document.getElementById('option3'),
    document.getElementById('option4')
];
let score = 0;
let randomQuestion = 0;
let questionArray = [];
let questionMaxIndex = 0;
let randomQuestionIndex = 0;
let scoreDisplay = document.getElementById('score');
function loadQuestions() {
    fetch('source/questions.json')
        .then(res => res.json())
        .then(questions => {
            console.log('Questions loaded:', questions);
            for (const question of questions) {
                questionMaxIndex++;
                questionArray.push(question);
            }
            randomQuestionIndex = Math.floor(Math.random() * questionMaxIndex);
            let randomQuestion = questionArray[randomQuestionIndex];
            questionText.textContent = randomQuestion.question;
            option1.textContent = randomQuestion.options.A;
            option2.textContent = randomQuestion.options.B;
            option3.textContent = randomQuestion.options.C;
            option4.textContent = randomQuestion.options.D;
            countdownNextQuestion(10, 'Time left: ');
        })
        .catch(err => console.error('Error loading questions: ', err));

    }

async function checkAnswer(selectedOption, questionId) {
    if (selectedOption == questionArray[randomQuestionIndex].answer) {
        questionId.classList.remove('bg-blue-500', 'hover:bg-blue-600', 'active:bg-blue-700');
        questionId.classList.add('bg-green-500');  
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
        disableButtons();
        showCorrectAnswer();
        await countdownNextQuestion(5, 'Next question in: ');
    } else {
        questionId.classList.remove('bg-blue-500', 'hover:bg-blue-600', 'active:bg-blue-700');
        questionId.classList.add('bg-red-500');
        disableButtons();
        showCorrectAnswer();
        await countdownNextQuestion(5, 'Next question in: ');
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
        option.classList.add('bg-blue-500', 'hover:bg-blue-600', 'active:bg-blue-700');
    }
}
function showCorrectAnswer() {
    let correctAnswer = questionArray[randomQuestionIndex].answer;
    for (let option of questionOptions) {
        if (option.textContent === questionArray[randomQuestionIndex].options[correctAnswer]) {
            option.classList.remove('bg-blue-500', 'hover:bg-blue-600', 'active:bg-blue-700');
            option.classList.add('bg-green-500');
        }
    }
}
let countdownUse = false;
let countdownInterval;
function countdownNextQuestion(countdownDuration, countdownExtraText) {
    let timerText = document.getElementById('timerText');
    timerText.textContent = countdownExtraText;
    let timer = document.getElementById('timer');
    let timeLeft = countdownDuration;
    timer.textContent = timeLeft;
    if (countdownUse) {
        clearInterval(countdownInterval);
    }
    countdownUse = true;
    countdownInterval = setInterval(() => {
        timeLeft--;
        timer.textContent = timeLeft;
        console.log('Countdown: ', timeLeft);
        if (timeLeft <= -1) {
            countdownUse = false;
            clearInterval(countdownInterval);
            resetButtonColors();
            loadQuestions();
            enableButtons();
        }
    }, 1000);
    
}


