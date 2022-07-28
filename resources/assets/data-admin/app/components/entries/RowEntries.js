import React from 'react';

import { Row, Col } from 'react-bootstrap';
import PieChartEntries from 'components/entries/PieChartEntries';
import TableEntries from 'components/entries/TableEntries';

const RowEntries = ({ data }) => {

    return (
        <Row className="animated fadeIn">
                    <Col xs={12} md={8} lg={8}>
                        <TableEntries stats={data} />
                    </Col>
                    <Col xs={12} md={4} lg={4}>
                        <PieChartEntries stats={data} />
                    </Col>
                </Row>
    );
};

export default RowEntries;
