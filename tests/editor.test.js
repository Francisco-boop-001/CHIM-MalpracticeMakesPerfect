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
  assert.equal(findButton(fixture.root, 'Create manual draft')?.disabled, true);
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
    ['nearby first and iterative authoring preserves all working fields', testAuthoring],
    ['model error readback preserves unsaved input without retry', testAuthoringFailure],
    ['unchanged proposal shows no field changes and cannot apply', testUnchangedProposal],
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

async function testAuthoring() {
  const root = makeRoot();
  const fields = Object.fromEntries(BrainwashEditor.fields.map((field) => [field, `old ${field}`]));
  const calls = [];
  const editor = BrainwashEditor.mount(root, { request: async (operation, payload) => {
    calls.push({ operation, payload });
    if (operation === 'brainwash.catalog.read') return { actors: [{ actorKey: 'npc:1', label: 'Alda' }], limited: false, state: 'ready', source: 'nearby', observedAt: 1000 };
    if (operation === 'brainwash.card.read') return { actorKey: 'npc:1', label: 'Alda', scope: 'a', revision: 'a'.repeat(64), fields, beliefs: '', draftId: null, lastOperation: null };
    if (operation === 'brainwash.draft') return { draftId: 'draft-a', before: { core: fields.core }, changes: { core: 'AI core' }, origin: 'ai', authoring: { reply: 'A more coherent card.' + 'x'.repeat(1200), evidence: [], historyCount: 0, historyTruncated: false } };
    throw new Error('Unexpected write');
  } });
  await settle();
  assert.deepEqual(calls[0].payload, { nearby: true });
  assert.ok(findButton(root, 'All stored NPCs'));
  const manual = root.descendants().find((node) => node.tagName === 'DETAILS');
  assert.ok(manual, 'optional manual editor uses details');
  assert.equal(Boolean(manual.open), false, 'manual editor starts collapsed');
  assert.ok(manual.children.some((node) => node.tagName === 'SUMMARY'));
  const goals = root.descendants().find((node) => node.getAttribute('aria-label') === 'goals text');
  goals.value = 'Unsaved personal goal'; await goals.fire('input');
  const includeGoals = findFieldCheckbox(root, 'goals'); includeGoals.checked = false; await includeGoals.fire('change');
  const instruction = root.descendants().find((node) => node.getAttribute('aria-label') === 'Creative direction');
  const flattened = root.descendants();
  assert.ok(flattened.indexOf(instruction) < flattened.indexOf(manual), 'AI instruction precedes manual editor');
  assert.ok(flattened.indexOf(findButton(root, 'Develop card with AI')) < flattened.indexOf(manual), 'AI action precedes manual editor');
  instruction.value = 'Develop her doubts' + 'x'.repeat(1200); await instruction.fire('input');
  await findButton(root, 'Develop card with AI').fire('click');
  const first = calls.find((call) => call.operation === 'brainwash.draft').payload;
  assert.equal(first.authoring.workingCard.goals, 'Unsaved personal goal');
  assert.equal(first.authoring.historyLimit, 0);
  assert.equal(first.changes.goals, undefined);
  assert.match(root.textContent, /A more coherent card/);
  assert.equal(root.descendants().find((node) => node.getAttribute('aria-label') === 'core text').value, 'AI core');
  assert.equal(root.descendants().find((node) => node.getAttribute('aria-label') === 'goals text').value, 'Unsaved personal goal');
  const history = root.descendants().find((node) => node.tagName === 'LABEL' && node.textContent.startsWith('Use recent relevant')).children[0];
  history.checked = true; await history.fire('change');
  await findButton(root, 'Refine proposal').fire('click');
  const second = calls.filter((call) => call.operation === 'brainwash.draft')[1].payload;
  assert.equal(second.authoring.workingCard.core, 'AI core');
  assert.equal(second.authoring.conversation.length, 2);
  assert.ok(second.authoring.conversation.every((turn) => turn.content.length > 1200));
  assert.equal(second.authoring.historyLimit, 50);
  assert.equal(calls.filter((call) => call.operation === 'brainwash.apply').length, 0);
  const turn = root.descendants().find((node) => node.getAttribute('aria-label') === 'user conversation turn');
  turn.value = 'é'.repeat(1001); await turn.fire('input');
  await findButton(root, 'Refine proposal').fire('click');
  assert.equal(calls.filter((call) => call.operation === 'brainwash.draft').length, 2);
  assert.match(root.textContent, /Conversation exceeds/);
  assert.equal(root.descendants().find((node) => node.getAttribute('aria-label') === 'user conversation turn').value, 'é'.repeat(1001));
  editor.reset();
}

async function testAuthoringFailure() {
  const root = makeRoot(); let drafts = 0; let failRead = false;
  const fields = Object.fromEntries(BrainwashEditor.fields.map((field) => [field, `old ${field}`]));
  BrainwashEditor.mount(root, { request: async (operation) => {
    if (operation === 'brainwash.catalog.read') return { actors: [{ actorKey: 'npc:1', label: 'Alda' }], limited: false, state: 'ready' };
    if (operation === 'brainwash.card.read') {
      if (failRead) throw new Error('Readback failed');
      return { actorKey: 'npc:1', label: 'Alda', scope: 'a', revision: 'a'.repeat(64), fields, beliefs: '', draftId: null, lastOperation: null };
    }
    if (operation === 'brainwash.draft') { drafts++; throw new Error('Model rejected'); }
    throw new Error('Unexpected write');
  } });
  await settle();
  const core = root.descendants().find((node) => node.getAttribute('aria-label') === 'core text');
  core.value = 'My unsaved core'; await core.fire('input');
  const instruction = root.descendants().find((node) => node.getAttribute('aria-label') === 'Creative direction');
  instruction.value = 'My direction'; await instruction.fire('input');
  const goals = findFieldCheckbox(root, 'goals'); goals.checked = false; await goals.fire('change');
  const beliefs = root.descendants().find((node) => node.getAttribute('aria-label') === 'User-authored fictional belief text');
  beliefs.value = 'Unsaved belief'; await beliefs.fire('input');
  const includeBeliefs = findFieldCheckbox(root, 'User-authored fictional belief'); includeBeliefs.checked = true; await includeBeliefs.fire('change');
  await findButton(root, 'Develop card with AI').fire('click');
  assert.equal(drafts, 1);
  failRead = true;
  await findButton(root, 'Refresh').fire('click');
  assert.match(root.textContent, /Mutation is locked until an explicit readback/);
  failRead = false;
  await findButton(root, 'Refresh').fire('click');
  assert.equal(root.descendants().find((node) => node.getAttribute('aria-label') === 'core text').value, 'My unsaved core');
  assert.equal(root.descendants().find((node) => node.getAttribute('aria-label') === 'Creative direction').value, 'My direction');
  assert.equal(root.descendants().find((node) => node.getAttribute('aria-label') === 'User-authored fictional belief text').value, 'Unsaved belief');
  assert.equal(findFieldCheckbox(root, 'goals').checked, false);
  assert.equal(findFieldCheckbox(root, 'User-authored fictional belief').checked, true);
  assert.equal(drafts, 1);
}

async function testUnchangedProposal() {
  const root = makeRoot();
  let proposedBeliefs = '';
  const fields = Object.fromEntries(BrainwashEditor.fields.map((field) => [field, `old ${field}`]));
  BrainwashEditor.mount(root, { request: async (operation) => {
    if (operation === 'brainwash.catalog.read') return { actors: [{ actorKey: 'npc:1', label: 'Alda' }], state: 'ready', limited: false };
    if (operation === 'brainwash.card.read') return { actorKey: 'npc:1', label: 'Alda', scope: 'a', revision: 'a'.repeat(64), fields, beliefs: '', draftId: null, lastOperation: null };
    if (operation === 'brainwash.draft') return { draftId: 'unchanged', before: { ...fields }, changes: { ...fields }, beliefsBefore: '', beliefs: proposedBeliefs, origin: 'ai', authoring: { reply: 'The card already fits.', evidence: [], historyCount: 0, historyTruncated: false } };
    throw new Error('No apply expected');
  } });
  await settle();
  await findButton(root, 'Develop card with AI').fire('click');
  assert.equal(findButton(root, 'Apply accepted changes').disabled, true);
  assert.doesNotMatch(root.textContent, /Accept this field/);
  assert.match(root.textContent, /No field changes proposed/);
  proposedBeliefs = 'A new fictional belief';
  await findButton(root, 'Refine proposal').fire('click');
  assert.equal(findButton(root, 'Apply accepted changes').disabled, false);
  assert.doesNotMatch(root.textContent, /Accept this field/);
  const acceptBeliefs = root.descendants().find((node) => node.tagName === 'LABEL' && node.textContent === 'Accept this beliefs change').children[0];
  acceptBeliefs.checked = false; await acceptBeliefs.fire('change');
  assert.equal(findButton(root, 'Apply accepted changes').disabled, true);
}
