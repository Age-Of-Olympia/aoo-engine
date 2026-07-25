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

/* ============================================================
 * Boîtes de dialogue mutualisées — remplacent alert / confirm /
 * prompt natifs par des modales du jeu, partout.
 *
 *   aooAlert(message)                        -> Promise<void>
 *   aooConfirm(message)                      -> Promise<boolean>
 *   aooPrompt(message, valeurParDefaut)      -> Promise<string|null>
 *
 * Différence de contrat avec les natifs : PAS bloquantes. alert()
 * est remplacé globalement (aucun code ne lit son retour) ; confirm
 * et prompt exigent la migration des appelants vers .then().
 * ============================================================ */

(function () {

  function buildDialog(message, options) {
    const bg = document.createElement('div');
    bg.className = 'modal-bg aoo-dialog-bg';
    bg.style.display = 'flex';

    const box = document.createElement('div');
    box.className = 'modal aoo-dialog';

    const text = document.createElement('div');
    text.className = 'aoo-dialog-text';
    /* textContent : les messages viennent parfois du serveur — pas
     * d'injection HTML, les \n sont rendus par white-space:pre-line */
    text.textContent = String(message == null ? '' : message);
    box.appendChild(text);

    let input = null;
    if (options.input) {
      input = document.createElement('input');
      input.type = 'text';
      input.className = 'aoo-dialog-input';
      input.value = options.defaultValue == null ? '' : String(options.defaultValue);
      box.appendChild(input);
    }

    /* Choix parmi une liste : un <select> plutôt qu'un champ libre —
     * quand la réponse EST une des options, la saisir à la main est une
     * source d'erreur, et l'appelant devrait revalider. textContent sur
     * les libellés : ils décrivent parfois des objets nommés par des
     * joueurs. */
    if (options.choices) {
      input = document.createElement('select');
      input.className = 'aoo-dialog-input';
      options.choices.forEach(function (choice) {
        const opt = document.createElement('option');
        opt.value = String(choice.value);
        opt.textContent = String(choice.label);
        if (options.defaultValue != null && String(choice.value) === String(options.defaultValue)) {
          opt.selected = true;
        }
        input.appendChild(opt);
      });
      box.appendChild(input);
    }

    const buttons = document.createElement('div');
    buttons.className = 'aoo-dialog-buttons';

    const okBtn = document.createElement('button');
    okBtn.textContent = options.okLabel || 'OK';
    okBtn.className = 'aoo-dialog-ok';
    buttons.appendChild(okBtn);

    let cancelBtn = null;
    if (options.cancel) {
      cancelBtn = document.createElement('button');
      cancelBtn.textContent = 'Annuler';
      cancelBtn.className = 'aoo-dialog-cancel';
      buttons.appendChild(cancelBtn);
    }

    box.appendChild(buttons);
    bg.appendChild(box);
    document.body.appendChild(bg);

    return new Promise(function (resolve) {
      let done = false;

      function close(value) {
        if (done) { return; }
        done = true;
        document.removeEventListener('keydown', onKey, true);
        bg.remove();
        resolve(value);
      }

      function okValue() {
        return input ? input.value : true;
      }

      function onKey(e) {
        if (e.key === 'Escape') {
          e.preventDefault();
          e.stopPropagation();
          close(options.cancelValue);
        } else if (e.key === 'Enter' && (input === null || e.target === input)) {
          e.preventDefault();
          e.stopPropagation();
          close(okValue());
        }
      }

      okBtn.addEventListener('click', function () { close(okValue()); });
      if (cancelBtn) {
        cancelBtn.addEventListener('click', function () { close(options.cancelValue); });
      }
      /* Clic sur le fond = annulation (comme Échap) */
      bg.addEventListener('click', function (e) {
        if (e.target === bg) { close(options.cancelValue); }
      });
      document.addEventListener('keydown', onKey, true);

      (input || okBtn).focus();
      if (input) { input.select(); }
    });
  }

  window.aooAlert = function (message) {
    return buildDialog(message, { cancel: false, cancelValue: undefined });
  };

  window.aooConfirm = function (message) {
    return buildDialog(message, { cancel: true, cancelValue: false });
  };

  /**
   * Choix dans une liste. choices : [{value, label}].
   * Résout la valeur choisie, ou null si annulé.
   */
  window.aooChoose = function (message, choices, defaultValue) {
    return buildDialog(message, {
      cancel: true,
      cancelValue: null,
      choices: choices,
      defaultValue: defaultValue
    });
  };

  window.aooPrompt = function (message, defaultValue) {
    return buildDialog(message, {
      cancel: true,
      cancelValue: null,
      input: true,
      defaultValue: defaultValue
    });
  };

  /* alert() natif remplacé partout : son retour n'est jamais lu, la
   * version modale est donc compatible — à la non-blocance près, les
   * enchaînements « alert puis reload » sont migrés vers
   * aooAlert().then(...). Repli natif si le DOM n'est pas prêt. */
  const nativeAlert = window.alert ? window.alert.bind(window) : function () {};
  window.alert = function (message) {
    if (!document.body) {
      nativeAlert(message);
      return;
    }
    window.aooAlert(message);
  };

})();

  