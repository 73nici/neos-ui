import produce from 'immer';
import assignIn from 'lodash.assignin';
import {action as createAction, ActionType} from 'typesafe-actions';
import {NodeContextPath} from '@neos-project/neos-ts-interfaces';

import {actionTypes as system, InitAction} from '../../System';
import {WorkspaceName} from '@neos-project/neos-ts-interfaces';

import * as selectors from './selectors';

export interface WorkspaceInformation {
    name: WorkspaceName;
    publishableNodes: Array<{
        contextPath: NodeContextPath;
        documentContextPath: NodeContextPath;
    }>;
    baseWorkspace: WorkspaceName;
    readOnly?: boolean;
    status?: string;
}

export const PublishDiscardSope = {
    SITE: 0,
    DOCUMENT: 1
} as const;

export type PublishDiscardScope = typeof PublishDiscardSope;

export interface State extends Readonly<{
    personalWorkspace: WorkspaceInformation;
    scope: null | PublishDiscardScope;
}> {}

export const defaultState: State = {
    personalWorkspace: {
        name: '',
        publishableNodes: [],
        baseWorkspace: '',
        status: ''
    },
    scope: null
};

export enum actionTypes {
    UPDATE = '@neos/neos-ui/CR/Workspaces/UPDATE',
    PUBLISH_STARTED = '@neos/neos-ui/CR/Workspaces/PUBLISH_STARTED',
    PUBLISH_FINISHED = '@neos/neos-ui/CR/Workspaces/PUBLISH_FINISHED',
    DISCARD_STARTED = '@neos/neos-ui/CR/Workspaces/DISCARD_STARTED',
    DISCARD_ABORTED = '@neos/neos-ui/CR/Workspaces/DISCARD_ABORTED',
    DISCARD_CONFIRMED = '@neos/neos-ui/CR/Workspaces/DISCARD_CONFIRMED',
    DISCARD_FINISHED = '@neos/neos-ui/CR/Workspaces/DISCARD_FINISHED',
    CHANGE_BASE_WORKSPACE = '@neos/neos-ui/CR/Workspaces/CHANGE_BASE_WORKSPACE',
    REBASE_WORKSPACE = '@neos/neos-ui/CR/Workspaces/REBASE_WORKSPACE'
}

export type Action = ActionType<typeof actions>;

/**
 * Updates the data of a workspace
 */
const update = (data: WorkspaceInformation) => createAction(actionTypes.UPDATE, data);

/**
 * Publishes all changes in the given scope
 */
const publish = (scope: PublishDiscardScope) => createAction(actionTypes.PUBLISH_STARTED, {scope});

/**
 * Finish the ongoing publish
 */
const finishPublish = () => createAction(actionTypes.PUBLISH_FINISHED);

/**
 * Discards all changes in the given scope
 */
const discard = (scope: PublishDiscardScope) => createAction(actionTypes.DISCARD_STARTED, {scope});

/**
 * Abort the ongoing node discard workflow
 */
const abortDiscard = () => createAction(actionTypes.DISCARD_ABORTED);

/**
 * Confirm the ongoing discard
 */
const confirmDiscard = () => createAction(actionTypes.DISCARD_CONFIRMED);

/**
 * Finish the ongoing discard
 */
const finishDiscard = () => createAction(actionTypes.DISCARD_FINISHED);

/**
 * Change base workspace
 */
const changeBaseWorkspace = (name: string) => createAction(actionTypes.CHANGE_BASE_WORKSPACE, name);

/**
 * Rebase the user workspace
 */
const rebaseWorkspace = (name: string) => createAction(actionTypes.REBASE_WORKSPACE, name);

//
// Export the actions
//
export const actions = {
    update,
    publish,
    finishPublish,
    discard,
    abortDiscard,
    confirmDiscard,
    finishDiscard,
    changeBaseWorkspace,
    rebaseWorkspace
};

//
// Export the reducer
//
export const reducer = (state: State = defaultState, action: InitAction | Action) => produce(state, draft => {
    switch (action.type) {
        case system.INIT: {
            draft.personalWorkspace = action.payload.cr.workspaces.personalWorkspace;
            break;
        }
        case actionTypes.UPDATE: {
            draft.personalWorkspace = assignIn(draft.personalWorkspace, action.payload);
            break;
        }
        case actionTypes.PUBLISH_STARTED:
        case actionTypes.DISCARD_STARTED: {
            draft.scope = action.payload.scope;
            break;
        }
        case actionTypes.PUBLISH_FINISHED:
        case actionTypes.DISCARD_ABORTED:
        case actionTypes.DISCARD_FINISHED: {
            draft.scope = null;
            break;
        }
    }
});

//
// Export the selectors
//
export {selectors};
