(function () {
  if (window.rebelMyFormsLoaded || !window.addEventListener || !document.querySelectorAll) {
      return;
  }
  window.rebelMyFormsLoaded = true;

  var MAX_FORMS_PER_PAGE = 20;
  var MAX_FIELDS = 50;
  var MAX_FORM_NAME = 100;
  var MAX_FIELD_NAME = 60;

  // How long a request waits for a tracker that knows its website.
  var RETRY_MS = 500;
  var MAX_RETRIES = 20;

  var NOT_A_FIELD = /^(hidden|submit|button|reset|image)$/;

  var forms = [];
  var waiting = [];
  var retries = 0;

  function attribute(element, name) {
      return (element.getAttribute && element.getAttribute(name)) || '';
  }

  function ignored(element) {
      return !!element.hasAttribute && element.hasAttribute('data-rebel-ignore');
  }

  // Read as attributes: a field named "name" or "id" hides those properties of its form.
  function formName(form) {
      return (attribute(form, 'data-rebel-form') || attribute(form, 'id') || attribute(form, 'name')
          || attribute(form, 'action').split(/[?#]/)[0] || 'unnamed').substring(0, MAX_FORM_NAME);
  }

  function fieldName(element) {
      return (attribute(element, 'data-rebel-field') || attribute(element, 'name') || attribute(element, 'id')
          || element.type || 'unnamed').substring(0, MAX_FIELD_NAME);
  }

  function isField(element) {
      return !!element && !!element.form && /^(INPUT|SELECT|TEXTAREA)$/.test(element.tagName)
          && !NOT_A_FIELD.test(element.type) && !ignored(element);
  }

  // The value is looked at here and never sent.
  function isBlank(element) {
      return element.type === 'checkbox' || element.type === 'radio' ? !element.checked : !element.value;
  }

  function readyTrackers() {
      var ready = [];
      var trackers = window.Matomo && window.Matomo.getAsyncTrackers ? window.Matomo.getAsyncTrackers() : [];
      for (var i = 0; i < trackers.length; i++) {
          if (trackers[i].getSiteId() && trackers[i].getTrackerUrl()) {
              ready.push(trackers[i]);
          }
      }
      return ready;
  }

  // The tracker of a page can be set up after this script, by a tag manager for one.
  function flush() {
      var trackers = readyTrackers();
      if (!trackers.length) {
          if (retries++ < MAX_RETRIES) {
              setTimeout(flush, RETRY_MS);
          } else {
              waiting = [];
          }
          return;
      }
      while (waiting.length) {
          var request = waiting.shift();
          for (var i = 0; i < trackers.length; i++) {
              trackers[i].trackRequest(request);
          }
      }
  }

  // The fields of a radio group share a name and are one field.
  function fieldOf(state, element) {
      var name = fieldName(element);
      for (var i = 0; i < state.fields.length; i++) {
          if (state.fields[i].name === name) {
              return state.fields[i];
          }
      }
      if (state.fields.length >= MAX_FIELDS) {
          return null;
      }
      var field = { name: name, time: 0, changes: 0, focuses: 0, focusAt: 0, blank: 1 };
      state.fields.push(field);
      return field;
  }

  // Sends what is known about a form so far. The server keeps the latest of a page view.
  function send(state) {
      var now = Date.now();
      var fields = [];
      var i;

      if (state.started) {
          for (i = 0; i < state.fields.length; i++) {
              state.fields[i].blank = 1;
          }
          var elements = state.form.elements || [];
          for (i = 0; i < elements.length; i++) {
              var field = isField(elements[i]) && fieldOf(state, elements[i]);
              if (field && !isBlank(elements[i])) {
                  field.blank = 0;
              }
          }
          for (i = 0; i < state.fields.length; i++) {
              var f = state.fields[i];
              fields.push([f.name, f.time + (f.focusAt ? now - f.focusAt : 0), f.changes, f.focuses, f.blank]);
          }
      }

      var request = 'ca=1&rmf_n=' + encodeURIComponent(state.name)
          + '&rmf_s=' + state.started
          + '&rmf_c=' + state.submitted
          + '&rmf_t=' + (state.lastAt - state.firstAt)
          + '&rmf_l=' + encodeURIComponent(state.last)
          + '&rmf_f=' + encodeURIComponent(JSON.stringify(fields));
      if (request === state.sent) {
          return;
      }
      state.sent = request;

      waiting.push(request);
      if (waiting.length === 1) {
          flush();
      }
  }

  // A form is counted as seen the first time it is met here.
  function stateOf(form) {
      for (var i = 0; i < forms.length; i++) {
          if (forms[i].form === form) {
              return forms[i];
          }
      }
      if (forms.length >= MAX_FORMS_PER_PAGE || ignored(form)) {
          return null;
      }
      var state = { form: form, name: formName(form), started: 0, submitted: 0, firstAt: 0, lastAt: 0, fields: [], last: '', sent: '' };
      forms.push(state);
      send(state);
      return state;
  }

  function start(state, now) {
      if (!state.started) {
          state.started = 1;
          state.firstAt = now;
      }
      state.lastAt = now;
  }

  // The field an event is about, with its form marked as started.
  function touched(event) {
      var element = event.target;
      var state = isField(element) ? stateOf(element.form) : null;
      var field = state && fieldOf(state, element);
      if (!field) {
          return null;
      }
      start(state, Date.now());
      state.last = field.name;
      return field;
  }

  function onFocusIn(event) {
      var field = touched(event);
      if (field) {
          field.focuses++;
          field.focusAt = Date.now();
      }
  }

  function onFocusOut(event) {
      var field = touched(event);
      if (field && field.focusAt) {
          field.time += Date.now() - field.focusAt;
          field.focusAt = 0;
      }
  }

  function onChange(event) {
      var field = touched(event);
      if (field) {
          field.changes++;
      }
  }

  function onSubmit(event) {
      var state = event.target && event.target.tagName === 'FORM' ? stateOf(event.target) : null;
      if (state) {
          start(state, Date.now());
          state.submitted = 1;
          send(state);
      }
  }

  // The forms that were started and left as they are.
  function onLeave() {
      for (var i = 0; i < forms.length; i++) {
          if (forms[i].started) {
              send(forms[i]);
          }
      }
  }

  function onVisibilityChange() {
      if (document.visibilityState === 'hidden') {
          onLeave();
      }
  }

  function findForms() {
      var found = document.querySelectorAll('form');
      for (var i = 0; i < found.length; i++) {
          stateOf(found[i]);
      }
  }

  // A failure here must never break the form of the website.
  function safely(listener) {
      return function (event) {
          try {
              listener(event);
          } catch (e) {
              // nothing to do
          }
      };
  }

  document.addEventListener('focusin', safely(onFocusIn), true);
  document.addEventListener('focusout', safely(onFocusOut), true);
  document.addEventListener('change', safely(onChange), true);
  document.addEventListener('submit', safely(onSubmit), true);
  document.addEventListener('visibilitychange', safely(onVisibilityChange));
  window.addEventListener('pagehide', safely(onLeave));

  if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', safely(findForms));
  } else {
      safely(findForms)();
  }
})();
