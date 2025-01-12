  // Show a modal (making its .modal-bg visible)
  function showModal(modalElement) {
    modalElement.style.display = 'flex';
  }

  // Hide a modal
  function hideModal(modalElement) {
    modalElement.style.display = 'none';
  }

  function bindModalButton(modal, reloadOnClose){
    // Hide result modal on "Close"
    const closeBtn = modal.querySelector('.closeButton');
    closeBtn.addEventListener('click', () => {
      hideModal(modal);
      if(reloadOnClose){
        window.location.reload(true);
      }
    });

    $(window).click(function (e) {
      if (e.target.id === modal.id) {
        hideModal(modal);
        if(reloadOnClose){
          window.location.reload(true);
        }
      }
    });
  
  }

  