<script>
     function toggleFields() {
          const type = document.getElementById('question_type').value;
          const optionsBox = document.getElementById('optionsBox');
          const trueFalseBox = document.getElementById('trueFalseBox');
          const answerBox = document.getElementById('answerBox');

          optionsBox.style.display = ['single_choice','multiple_choice'].includes(type) ? 'block' : 'none';
          trueFalseBox.style.display = type === 'true_false' ? 'block' : 'none';
          answerBox.style.display = ['short_answer','fill_blank'].includes(type) ? 'block' : 'none';

          // adjust correct input types inside optionsList
          const optionRows = document.querySelectorAll('#optionsList .option-row');
          optionRows.forEach((row, idx) => {
               const wrap = row.querySelector('.correct-wrap');
               wrap.innerHTML = '';
               if (type === 'single_choice') {
                    const r = document.createElement('input');
                    r.type = 'radio';
                    r.name = 'correct_single';
                    r.value = idx;
                    r.className = 'form-check-input';
                    // hidden checkbox to preserve server friendly correct[] array
                    const hidden = document.createElement('input');
                    hidden.type = 'checkbox';
                    hidden.name = 'correct[]';
                    hidden.value = idx;
                    hidden.style.display = 'none';
                    r.addEventListener('change', function() {
                         document.querySelectorAll('input[name="correct[]"]').forEach(cb => cb.checked = false);
                         if (r.checked) hidden.checked = true;
                    });
                    // preserve checked state from server if present
                    if (document.querySelector('input[name="correct[]"][value="'+idx+'"]')?.checked) {
                         r.checked = true;
                         hidden.checked = true;
                    }
                    wrap.appendChild(r);
                    wrap.appendChild(hidden);
               } else if (type === 'multiple_choice') {
                    const cb = document.createElement('input');
                    cb.type = 'checkbox';
                    cb.name = 'correct[]';
                    cb.value = idx;
                    cb.className = 'form-check-input';
                    // preserve checked state from server if present
                    if (document.querySelector('input[name="correct[]"][value="'+idx+'"]')?.checked) cb.checked = true;
                    wrap.appendChild(cb);
               } else {
                    wrap.innerHTML = '';
               }
          });
     }

     (function () {
          'use strict'
          const form = document.getElementById('addQuestionForm');
          if (!form) return;

          form.addEventListener('submit', function (event) {
          if (!form.checkValidity()) {
               event.preventDefault();
               event.stopPropagation();
               form.classList.add('was-validated');
               return;
          }

          const type = document.getElementById('question_type').value;
          if (['single_choice','multiple_choice'].includes(type)) {
               const optionInputs = document.querySelectorAll('#optionsList input[name="options[]"]');
               let nonEmpty = 0;
               optionInputs.forEach(i => { if (i.value.trim() !== '') nonEmpty++; });
               if (nonEmpty < 2) {
                    event.preventDefault();
                    event.stopPropagation();
                    showAddError('At least two non-empty options are required.');
                    return;
               }

               const checked = Array.from(document.querySelectorAll('#optionsList input[name="correct[]"]')).some(cb => cb.checked);
               if (!checked) {
                    event.preventDefault();
                    event.stopPropagation();
                    showAddError('Mark at least one correct option.');
                    return;
               }

               if (type === 'single_choice') {
                    const checkedCount = Array.from(document.querySelectorAll('#optionsList input[name="correct[]"]')).filter(cb => cb.checked).length;
                    if (checkedCount !== 1) {
                         event.preventDefault();
                         event.stopPropagation();
                         showAddError('Single choice requires exactly one correct option.');
                         return;
                    }
               }
          }

          if (type === 'true_false') {
               const tf = document.getElementById('correct_tf').value;
               if (!tf) {
                    event.preventDefault();
                    event.stopPropagation();
                    showAddError('Select True or False.');
                    return;
               }
          }

          form.classList.add('was-validated');
          }, false);

          function showAddError(msg) {
          const el = document.getElementById('add-errors');
          el.innerHTML = '<div class="alert alert-danger">'+msg+'</div>';
          window.scrollTo({top: 0, behavior: 'smooth'});
          }
          })();
</script>