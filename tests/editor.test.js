'use strict';

const assert = require('node:assert/strict');
const BrainwashEditor = require('../server/assets/editor.js');

class Element {
  constructor(tagName, ownerDocument) {
    this.tagName = tagName.toUpperCase();
    this.ownerDocument = ownerDocument;
    this.children = [];
    this.listeners = {};
    this.attributes = {};
    this.disabled = false;
    this.value = '';
    this.checked = false;
    this._text = '';
  }

  set textContent(value) { this._text = String(value); }
  get textContent() { return this._text + this.children.map((child) => child.textContent).join(''); }
  appendChild(child) { this.children.push(child); return child; }
  replaceChildren(...children) { this.children = children; }
  addEventListener(name, callback) { (this.listeners[name] ||= []).push(callback); }
  setAttribute(name, value) { this.attributes[name] = String(value); }
  getAttribute(name) { return this.attributes[name] ?? null; }
  async fire(name) {
    for (const callback of this.listeners[name] || []) await callback({ target: this });
  }
  descendants() { return this.children.flatMap((child) => [child, ...child.descendants()]); }
}

function makeRoot() {
  const doc = { createElement: (tag) => new Element(tag, doc) };
  return new Element('main', doc);
}

function findButton(root, label) {
  return root.descendants().find((node) => node.tagName === 'BUTTON' && node.textContent === label);
}

function findFieldCheckbox(root, field) {
  return root.descendants().find((node) => node.tagName === 'FIELDSET'
    && node.children.some((child) => child.tagName === 'LEGEND' && child.textContent === field))
    ?.descendants().find((node) => node.tagName === 'INPUT' && node.type === 'checkbox');
}

function findTag(root, tagName) {
  return root.descendants().find((node) => node.tagName === tagName.toUpperCase());
}

async function settle() {
  for (let i = 0; i < 8; i += 1) await new Promise((resolve) => setTimeout(resolve, 0));
}

async function makeUncertainApply() {
  const root = makeRoot();
  const fields = Object.fromEntries(BrainwashEditor.fields.map((field) => [field, `old ${field}`]));
  let scope = 'timeline-a';
  let storedDraftId = null;
  let failRead = false;
  let confirmApplyReadback = false;
  let pendingOperationId = null;
  const request = async (operation, payload) => {
    if (operation === 'brainwash.catalog.read') return { actors: [{ actorKey: 'npc:1', label: 'Alda' }], limited: false };
    if (operation === 'brainwash.card.read') {
      if (failRead) throw new Error('temporary read failure');
      return {
        actorKey: 'npc:1', label: 'Alda', scope, revision: 'a'.repeat(64), fields: { ...fields },
        beliefs: '', draftId: storedDraftId,
        lastOperation: confirmApplyReadback ? { operationId: pendingOperationId, state: 'applied' } : null,
      };
    }
    if (operation === 'brainwash.draft') {
      storedDraftId = 'draft-1';
      return {
        actorKey: 'npc:1', scope, revision: payload.expectedRevision, draftId: storedDraftId,
        origin: 'manual', before: { core: fields.core }, changes: { core: 'new core' },
      };
    }
    if (operation === 'brainwash.apply') {
      pendingOperationId = payload.operationId;
      throw new Error('connection lost after request');
    }
    throw new Error(`Unexpected operation ${operation}`);
  };

  const editor = BrainwashEditor.mount(root, { request });
  await settle();
  assert.ok(findFieldCheckbox(root, 'core'), 'card should be loaded');
  const includeCore = findFieldCheckbox(root, 'core');
  includeCore.checked = true;
  await includeCore.fire('change');
  await findButton(root, 'Create manual draft').fire('click');
  await findButton(root, 'Apply accepted changes').fire('click');
  assert.match(root.textContent, /Mutation is locked until an explicit readback/);
  return {
    root,
    editor,
    setScope(value) { scope = value; if (value !== 'timeline-a') storedDraftId = null; },
    setFailRead(value) { failRead = value; },
    setConfirmApplyReadback(value) { confirmApplyReadback = value; },
  };
}

async function testUnresolvedReadFailureKeepsMutationLock() {
  const fixture = await makeUncertainApply();
  fixture.setFailRead(true);
  await findButton(fixture.root, 'Refresh').fire('click');
  assert.match(fixture.root.textContent, /Mutation is locked until an explicit readback/);
  assert.equal(findTag(fixture.root, 'select')?.disabled, true);
  assert.equal(findButton(fixture.root, 'Create manual draft'), undefined);
}

async function testSameValueAvailabilityKeepsUncertainMutationLocked() {
  const fixture = await makeUncertainApply();
  fixture.editor.setAvailable(true);
  await settle();
  assert.match(fixture.root.textContent, /Mutation is locked until an explicit readback/);
  assert.equal(findButton(fixture.root, 'Create manual draft')?.disabled, true);
}

async function testSameActorRestoreScopeChangeClearsOldUncertainState() {
  const fixture = await makeUncertainApply();
  fixture.setScope('timeline-b');
  await findButton(fixture.root, 'Refresh').fire('click');
  assert.doesNotMatch(fixture.root.textContent, /Mutation is locked until an explicit readback/);
  assert.equal(findButton(fixture.root, 'Create manual draft')?.disabled, false);
}

async function testConfirmedReadbackUnlocksMutation() {
  const fixture = await makeUncertainApply();
  fixture.setConfirmApplyReadback(true);
  await findButton(fixture.root, 'Refresh').fire('click');
  assert.doesNotMatch(fixture.root.textContent, /Mutation is locked until an explicit readback/);
  assert.equal(findButton(fixture.root, 'Undo last Malpractice Makes Perfect change')?.disabled, false);
}

async function testResetMakesPendingCatalogResponseStale() {
  const root = makeRoot();
  let resolveCatalog;
  const editor = BrainwashEditor.mount(root, {
    request: () => new Promise((resolve) => { resolveCatalog = resolve; }),
  });
  editor.reset();
  resolveCatalog({ actors: [{ actorKey: 'npc:1', label: 'Alda' }], limited: false });
  await settle();
  assert.match(root.textContent, /Editor reset/);
  assert.doesNotMatch(root.textContent, /Current card: Alda/);
}

async function main() {
  const tests = [
    ['read failure preserves unresolved mutation lock', testUnresolvedReadFailureKeepsMutationLock],
    ['same availability preserves unresolved mutation lock', testSameValueAvailabilityKeepsUncertainMutationLocked],
    ['same-actor scope change clears old uncertain state', testSameActorRestoreScopeChangeClearsOldUncertainState],
    ['confirmed readback unlocks mutation', testConfirmedReadbackUnlocksMutation],
    ['reset makes pending catalog response stale', testResetMakesPendingCatalogResponseStale],
  ];
  let failed = 0;
  for (const [name, test] of tests) {
    try {
      await test();
      process.stdout.write(`PASS ${name}\n`);
    } catch (error) {
      failed += 1;
      process.stderr.write(`FAIL ${name}: ${error.stack || error}\n`);
    }
  }
  if (failed) process.exitCode = 1;
}

main();
