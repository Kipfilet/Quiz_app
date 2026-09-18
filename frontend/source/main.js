fetch("frontend/source/questions.json")
  .then((response) => response.json())
  .then((data) => {
    const questionContainer = document.getElementById("question-container");
    const questionText = document.getElementById("question-text");
    const answerButtons = document.getElementById("answer-buttons");
  });