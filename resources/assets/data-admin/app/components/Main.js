import React from 'react';
import Stats from 'containers/Stats';

class Main extends React.Component {

    constructor(props) {
        super(props);
    }

    componentDidMount() {

    }

    render() {
        //todo show loader
        return (
            <div className="admin-stats">
                <Stats />
            </div>);
    }
}

export default Main;

