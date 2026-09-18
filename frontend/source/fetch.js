questionText = document.getElementById('questionText');
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
      })
      .catch(err => console.error('Error loading questions.json:', err));