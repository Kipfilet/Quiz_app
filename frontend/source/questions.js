let questionText = document.getElementById('questionText');
let isOver = false;
let questionOptions = [
    document.getElementById('option1'),
    document.getElementById('option2'),
    document.getElementById('option3'),
    document.getElementById('option4')
];
let score = 0;
let questionArray = [];
let questionMaxIndex = 0;
let randomQuestionIndex = 0;
let scoreDisplay = document.getElementById('score');
let correctAnswers = 0
function fetchQuestions(){
    fetch('source/questions.json')
        .then(res => res.json())
        .then(questions => {
            console.log('Questions loaded:', questions);
            for (const question of questions) {
                questionMaxIndex++;
                questionArray.push(question);
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
            countdownNextQuestion(10, 'Time left: ',"timer");
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
        correctAnswers++;
        disableButtons();
        showCorrectAnswer();
        await countdownNextQuestion(3, 'Next question in: ',"cooldown");
    } else {
        questionId.classList.remove('bg-blue-500', 'hover:bg-blue-600', 'active:bg-blue-700');
        questionId.classList.add('bg-red-500');
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
function countdownNextQuestion(countdownDuration, countdownExtraText, countdownType) {
    let timerText = document.getElementById('timerText');
    timerText.textContent = countdownExtraText;
    let timer = document.getElementById('timer');
    let timeLeft = countdownDuration;
    timer.textContent = timeLeft;
    if (countdownUse) {
        clearInterval(countdownInterval);
    }
    countdownUse = true;
    {
        countdownInterval = setInterval(() => {
        timeLeft--;
        timer.textContent = timeLeft;
        console.log('Countdown: ', timeLeft);
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
    if (heartCount > 1){
        heartContainer.children[heartCount - 1].ariaCurrent = "false";
        heartContainer.children[heartCount - 2].ariaCurrent = "true";
        heartCount--
    }
    else{
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

    
}  