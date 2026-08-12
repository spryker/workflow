'use strict';

// The state lives in a cookie (not localStorage) so Presentation/_partial/workflow-setup-info.twig
// can render the saved state server-side — this bundle loads in the footer, and applying the state
// here would flash the panel open before collapsing it.
const COOKIE_NAME = 'workflow-setup-info-visibility';
const COOKIE_MAX_AGE_SECONDS = 60 * 60 * 24 * 365;
const HIDDEN_STATE = 'hidden';
const VISIBLE_STATE = 'visible';

const WRAPPER_SELECTOR = '.js-workflow-wrapper';
const HIDE_BUTTON_SELECTOR = '.js-hide-info-visibility-button';
const SHOW_BUTTON_SELECTOR = '.js-show-info-visibility-button';

function saveState(state) {
    document.cookie = COOKIE_NAME + '=' + state + '; path=/; max-age=' + COOKIE_MAX_AGE_SECONDS + '; SameSite=Lax';
}

function init() {
    const wrapper = document.querySelector(WRAPPER_SELECTOR);
    const hideButton = document.querySelector(HIDE_BUTTON_SELECTOR);
    const showButton = document.querySelector(SHOW_BUTTON_SELECTOR);

    if (!wrapper || !hideButton || !showButton) {
        return;
    }

    hideButton.addEventListener('click', () => {
        wrapper.style.display = 'none';
        showButton.style.display = '';
        saveState(HIDDEN_STATE);
    });

    showButton.addEventListener('click', () => {
        wrapper.style.display = '';
        showButton.style.display = 'none';
        saveState(VISIBLE_STATE);
    });
}

init();
