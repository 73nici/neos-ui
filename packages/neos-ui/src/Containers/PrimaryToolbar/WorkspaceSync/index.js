/* eslint-disable complexity */
import React, {PureComponent} from 'react';
import PropTypes from 'prop-types';
import {connect} from 'react-redux';

import {actions, selectors} from '@neos-project/neos-ui-redux-store';
const {personalWorkspaceRebaseStatusSelector} = selectors.CR.Workspaces;
import {neos} from '@neos-project/neos-ui-decorators';
import {WorkspaceStatus} from '@neos-project/neos-ts-interfaces';

import Icon from '@neos-project/react-ui-components/src/Icon/';
import Button from '@neos-project/react-ui-components/src/Button/';
import WorkspaceSyncIcon from './WorkspaceSyncIcon';

import style from './style.module.css';

@connect(state => ({
    isOpen: state?.ui?.SyncWorkspaceModal?.isOpen,
    isSaving: state?.ui?.remote?.isSaving,
    isPublishing: state?.ui?.remote?.isPublishing,
    isDiscarding: state?.ui?.remote?.isDiscarding,
    personalWorkspaceStatus: personalWorkspaceRebaseStatusSelector(state)
}), {
    openModal: actions.UI.SyncWorkspaceModal.open
})
@neos(globalRegistry => ({
    i18nRegistry: globalRegistry.get('i18n')
}))

export default class WorkspaceSync extends PureComponent {
    static propTypes = {
        isOpen: PropTypes.bool.isRequired,
        isSaving: PropTypes.bool.isRequired,
        isPublishing: PropTypes.bool.isRequired,
        isDiscarding: PropTypes.bool.isRequired,
        openModal: PropTypes.func.isRequired,
        personalWorkspaceStatus: PropTypes.string.isRequired,
        i18nRegistry: PropTypes.object.isRequired
    };

    render() {
        const {
            personalWorkspaceStatus,
            openModal,
            isSaving,
            isOpen,
            isPublishing,
            isDiscarding,
            i18nRegistry
        } = this.props;

        if (personalWorkspaceStatus === WorkspaceStatus.UP_TO_DATE) {
            return (null);
        }

        const buttonLabel = i18nRegistry.translate(
            'syncPersonalWorkSpace',
            'Synchronize personal workspace', {}, 'Neos.Neos.Ui', 'Main');
        return (
            <div id="neos-WorkspaceSync" className={style.wrapper}>
                <Button
                    id="neos-workspace-rebase"
                    className={style.rebaseButton}
                    onClick={openModal}
                    disabled={isSaving || isOpen || isPublishing || isDiscarding}
                    style={personalWorkspaceStatus === WorkspaceStatus.OUTDATED ? 'warn' : 'error'}
                    hoverStyle={personalWorkspaceStatus === WorkspaceStatus.OUTDATED ? 'warn' : 'error'}
                    label={buttonLabel}
                >
                    {(isSaving || isPublishing || isDiscarding) ? (
                        <Icon icon="spinner" spin={true}/>
                    ) : (
                        <WorkspaceSyncIcon personalWorkspaceStatus={personalWorkspaceStatus}/>
                    )}
                </Button>
            </div>
        );
    }
}
