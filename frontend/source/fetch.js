questionText = document.getElementById('questionText');
questionOptions = [
    document.getElementById('option1'),
    document.getElementById('option2'),
    document.getElementById('option3'),
    document.getElementById('option4')
];
questionMaxIndex = 0;
fetch('source/questions.json')
      .then(res => res.json())
      .then(questions => {
        console.log('Questions loaded:', questions);
        for (const question of questions) {
            questionMaxIndex++;
            
        }
        randomQuestionIndex = Math.floor(Math.random() * questionMaxIndex);
        questionText.textContent = questions[randomQuestionIndex].question;
        option1.textContent = questions[randomQuestionIndex].options[0];
        option2.textContent = questions[randomQuestionIndex].options[1];
        option3.textContent = questions[randomQuestionIndex].options[2];
        option4.textContent = questions[randomQuestionIndex].options[3];
      })
      .catch(err => console.error('Error loading questions: ', err));