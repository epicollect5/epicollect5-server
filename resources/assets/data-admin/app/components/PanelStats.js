import React from 'react';
import { Panel } from 'react-bootstrap';
import Loader from 'components/Loader';
import RowEntries from 'components/entries/RowEntries';
import RowProjects from 'components/projects/RowProjects';
import RowUsers from 'components/users/RowUsers';
import ErrorView from 'components/ErrorView';

class PanelStats extends React.Component {

    constructor(props) {
        super(props);
    }

    static getDataView(data, type) {

        if (data.wasRejected) {
            return <ErrorView data={data} />;
        }

        switch (type) {

            case 'entries':
            {
                return <RowEntries data={data} />;
            }
            case 'projects':
            {
                return <RowProjects data={data} />;
            }
            case 'users':
            {
                return <RowUsers data={data} />;
            }
        }
    }

    render() {

        const data = this.props.data;
        const title = this.props.title;
        const type = this.props.type;
        const panelHeader = (
            <h3>{title}</h3>
        );

        //show a panel with table of data and pie chart
        return (
            <Panel className="stats-panel" header={panelHeader}>
            { data.wasRejected === null
                ?
                <Loader elementClass={'panel-loader'} />
                :
                PanelStats.getDataView(data, type)
            }

        </Panel>
        );
    }
}

export default PanelStats;

