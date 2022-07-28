import React from 'react';

import { Row, Col } from 'react-bootstrap';
import BarChartPlatformEntries from 'components/entries/BarChartPlatformEntries';
import TablePlatformEntries from 'components/entries/TablePlatformEntries';
import ErrorView from 'components/ErrorView';

const RowPlatformEntries = ({ data }) => {

    if (data.wasRejected) {
        return <ErrorView data={data} />;
    }

    return (
        <Row className="animated fadeIn">
            <Col xs={12} md={4} lg={4}>
                <TablePlatformEntries stats={data} />
            </Col>
            <Col xs={12} md={8} lg={8}>
                <BarChartPlatformEntries stats={data} />
            </Col>
        </Row>
    );
};

export default RowPlatformEntries;
