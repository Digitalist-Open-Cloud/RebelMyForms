import { assertEquals } from "jsr:@std/assert@1";

const field = (form, attributes = {}, extra = {}) => {
  const element = {
    tagName: "INPUT", type: "text", value: "", form,
    getAttribute: (name) => attributes[name] ?? null,
    hasAttribute: (name) => name in attributes,
    ...extra,
  };
  form.elements.push(element);
  return element;
};

const makeForm = (attributes = { id: "contact" }) => ({
  tagName: "FORM", elements: [],
  getAttribute: (name) => attributes[name] ?? null,
  hasAttribute: (name) => name in attributes,
});

// Runs tracker.js in a faked browser. Returns what to fire events with and the requests sent.
function load({ forms = [], siteId = 1, loadedBefore = false } = {}) {
  const listeners = {};
  const requests = [];
  const timers = [];
  const env = { siteId, requests, now: 1000 };
  const tracker = {
    getSiteId: () => env.siteId,
    getTrackerUrl: () => "https://m.example/matomo.php",
    trackRequest: (request) => requests.push(Object.fromEntries(new URLSearchParams(request))),
  };
  const listen = (type, listener) => { listeners[type] = listener; };
  const window = {
    rebelMyFormsLoaded: loadedBefore,
    addEventListener: listen,
    Matomo: { getAsyncTrackers: () => [tracker] },
  };
  const document = {
    readyState: "complete", visibilityState: "visible",
    addEventListener: listen,
    querySelectorAll: () => forms,
  };
  const src = Deno.readTextFileSync(new URL("../../tracker.js", import.meta.url));
  new Function("window", "document", "setTimeout", "Date", src)(
    window, document, (callback) => timers.push(callback), { now: () => env.now },
  );
  env.document = document;
  env.fire = (type, target, after = 0) => { env.now += after; listeners[type] && listeners[type]({ target }); };
  // Lets the time pass that the script waits for a tracker.
  env.wait = (times = 1) => { for (let i = 0; i < times && timers.length; i++) timers.shift()(); };
  env.last = () => requests[requests.length - 1];
  env.fields = () => JSON.parse(env.last().rmf_f);
  return env;
}

Deno.test("a form on the page is sent as seen", () => {
  const env = load({ forms: [makeForm(), makeForm({ name: "search" })] });

  assertEquals(env.requests, [
    { ca: "1", rmf_n: "contact", rmf_s: "0", rmf_c: "0", rmf_r: "0", rmf_t: "0", rmf_l: "", rmf_f: "[]" },
    { ca: "1", rmf_n: "search", rmf_s: "0", rmf_c: "0", rmf_r: "0", rmf_t: "0", rmf_l: "", rmf_f: "[]" },
  ]);
});

Deno.test("a form is named by its attribute, its ID, its name or where it is sent", () => {
  const env = load({ forms: [
    makeForm({ "data-rebel-form": "Signup", id: "f1" }),
    makeForm({ action: "/subscribe?token=abc#top" }),
    makeForm({}),
  ] });

  assertEquals(env.requests.map((request) => request.rmf_n), ["Signup", "/subscribe", "unnamed"]);
});

Deno.test("a submitted form is sent with what happened in its fields", () => {
  const form = makeForm();
  const name = field(form, { name: "name" });
  const email = field(form, { name: "email" });
  field(form, { name: "phone" });
  field(form, { name: "token" }, { type: "hidden", value: "secret" });
  const env = load({ forms: [form] });

  env.fire("focusin", name);
  name.value = "Ada";
  env.fire("change", name, 2000);
  env.fire("focusout", name);
  env.fire("focusin", email, 100);
  env.fire("focusout", email, 500);
  env.fire("focusin", email, 100);
  email.value = "ada@example.org";
  env.fire("change", email, 3000);
  env.fire("focusout", email);
  env.fire("submit", form, 300);

  assertEquals(env.requests.length, 2);
  assertEquals({ ...env.last(), rmf_f: "" }, { ca: "1", rmf_n: "contact", rmf_s: "1", rmf_c: "1", rmf_r: "0", rmf_t: "6000", rmf_l: "email", rmf_f: "" });
  assertEquals(env.fields(), [["name", 2000, 1, 1, 0, 0], ["email", 3500, 1, 2, 0, 0], ["phone", 0, 0, 0, 1, 0]]);
});

Deno.test("what a visitor typed is not sent", () => {
  const form = makeForm();
  const password = field(form, { name: "password" }, { type: "password" });
  const env = load({ forms: [form] });

  env.fire("focusin", password);
  password.value = "hunter2-secret";
  env.fire("change", password, 500);
  env.fire("submit", form);

  assertEquals(JSON.stringify(env.requests).includes("hunter2"), false);
});

Deno.test("a form that was started is sent when the visitor leaves, with the field they were in", () => {
  const form = makeForm();
  const name = field(form, { name: "name" });
  const email = field(form, { name: "email" });
  const env = load({ forms: [form] });

  env.fire("pagehide");
  assertEquals(env.requests.length, 1);

  env.fire("focusin", name);
  env.fire("focusout", name, 1000);
  env.fire("focusin", email, 0);
  env.fire("pagehide", undefined, 4000);

  assertEquals(env.last().rmf_s, "1");
  assertEquals(env.last().rmf_c, "0");
  assertEquals(env.last().rmf_l, "email");
  assertEquals(env.fields(), [["name", 1000, 0, 1, 1, 0], ["email", 4000, 0, 1, 1, 0]]);
});

Deno.test("a hidden tab sends the form, and nothing is sent twice", () => {
  const form = makeForm();
  const name = field(form, { name: "name" });
  const env = load({ forms: [form] });
  env.fire("focusin", name);
  env.fire("focusout", name, 1000);

  env.fire("visibilitychange");
  assertEquals(env.requests.length, 1);

  env.document.visibilityState = "hidden";
  env.fire("visibilitychange");
  env.fire("pagehide");
  assertEquals(env.requests.length, 2);
});

Deno.test("the radio buttons of a group are one field, filled in when one is checked", () => {
  const form = makeForm();
  const yes = field(form, { name: "answer" }, { type: "radio", checked: false });
  field(form, { name: "answer" }, { type: "radio", checked: false });
  field(form, { name: "terms" }, { type: "checkbox", checked: false });
  const env = load({ forms: [form] });

  yes.checked = true;
  env.fire("change", yes);
  env.fire("submit", form);

  assertEquals(env.fields(), [["answer", 0, 1, 0, 0, 0], ["terms", 0, 0, 0, 1, 0]]);
});

Deno.test("a field the browser refused and a form that is submitted again are counted", () => {
  const form = makeForm();
  const email = field(form, { name: "email" });
  const env = load({ forms: [form] });

  env.fire("invalid", email);
  env.fire("pagehide");
  assertEquals(env.last().rmf_c, "0");
  assertEquals(env.fields(), [["email", 0, 0, 0, 1, 1]]);

  env.fire("submit", form);
  assertEquals([env.last().rmf_c, env.last().rmf_r], ["1", "0"]);
  env.fire("submit", form);
  assertEquals([env.last().rmf_c, env.last().rmf_r], ["1", "1"]);
});

Deno.test("a form or a field that is marked to be ignored is left out", () => {
  const ignored = makeForm({ id: "login", "data-rebel-ignore": "" });
  const form = makeForm();
  const name = field(form, { name: "name" });
  const secret = field(form, { name: "card", "data-rebel-ignore": "" });
  const env = load({ forms: [ignored, form] });

  env.fire("focusin", field(ignored, { name: "user" }));
  env.fire("focusin", secret);
  assertEquals(env.requests.length, 1);

  env.fire("focusin", name);
  env.fire("submit", form);
  assertEquals(env.fields().map((f) => f[0]), ["name"]);
});

Deno.test("a form added after the page was loaded is counted when it is first used", () => {
  const env = load();
  const form = makeForm({ id: "late" });

  env.fire("focusin", field(form, { name: "q" }));
  env.fire("submit", form);

  assertEquals(env.requests.map((request) => [request.rmf_n, request.rmf_s, request.rmf_c]), [["late", "0", "0"], ["late", "1", "1"]]);
});

Deno.test("a form waits for a tracker that knows its website", () => {
  const env = load({ forms: [makeForm()], siteId: 0 });
  assertEquals(env.requests.length, 0);

  env.siteId = 1;
  env.wait();
  assertEquals(env.requests.length, 1);
});

Deno.test("the script does nothing the second time it is loaded", () => {
  const env = load({ forms: [makeForm()], loadedBefore: true });

  assertEquals(env.requests.length, 0);
});
