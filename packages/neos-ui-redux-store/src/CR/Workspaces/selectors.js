import {$get} from 'plow-js';
import {createSelector} from 'reselect';
import {documentNodeContextPathSelector} from './../Nodes/selectors';

export const personalWorkspaceNameSelector = $get('cr.workspaces.personalWorkspace.name');

export const baseWorkspaceSelector = $get('cr.workspaces.personalWorkspace.baseWorkspace');

export const isWorkspaceReadOnlySelector = $get('cr.workspaces.personalWorkspace.readOnly');

export const publishableNodesSelector = $get('cr.workspaces.personalWorkspace.publishableNodes');

export const publishableNodesInDocumentSelector = createSelector(
    [
        publishableNodesSelector,
        documentNodeContextPathSelector
    ],
    (publishableNodes, activeDocumentContextPath) => publishableNodes.filter(i => $get('documentContextPath', i) === activeDocumentContextPath)
);

export const makeIsDocumentNodeDirtySelector = () => createSelector(
    [
        publishableNodesSelector,
        (state, documentContextPath) => documentContextPath
    ],
    (publishableNodes, documentContextPath) => publishableNodes.filter(i => (
        $get('documentContextPath', i) === documentContextPath ||
        $get('contextPath', i) === documentContextPath
    )).count() > 0
);

export const makeIsContentNodeDirtySelector = () => createSelector(
    [
        publishableNodesSelector,
        (state, contextPath) => contextPath
    ],
    (publishableNodes, contextPath) => publishableNodes.filter(i => $get('contextPath', i) === contextPath).count() > 0
);
